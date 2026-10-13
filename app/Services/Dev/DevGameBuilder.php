<?php

declare(strict_types=1);

namespace App\Services\Dev;

use App\Core\Config;
use App\Services\Game\Abilities;
use App\Services\Game\GameEngine;
use DomainException;
use InvalidArgumentException;

/**
 * Fabrique des états de partie pour le Labo (piles imposées, graine, scénarios de fin). Ne touche ni à la session ni à la base :
 * DevTools installe le résultat.
 */
final class DevGameBuilder
{
    /** Taille des piles et de la cagnotte autorisées dans le Labo. */
    private const MAX_PILE = 60;
    private const MAX_POT = 10;

    /** @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id */
    public function __construct(
        private GameEngine $engine,
        private Abilities $abilities,
        private DevGuard $guard,
        private array $cards,
    ) {
    }

    /**
     * Une graine (`seed`) rend la partie reproductible : mêmes piles, mêmes choix de l'ordinateur.
     *
     * @param array{difficulty: string, player_count: int, ai_count: int, round: int, leader: string, pot: int, seed: ?int, player_top: int[], ai_top: int[]} $o
     * @return array<string, mixed> l'état prêt à installer
     */
    public function build(array $o): array
    {
        $this->guard->difficulty($o['difficulty']);
        $playerCount = $this->guard->between($o['player_count'], 1, self::MAX_PILE, 'Cartes du joueur');
        $aiCount = $this->guard->between($o['ai_count'], 1, self::MAX_PILE, 'Cartes de l\'ordinateur');
        $round = $this->guard->between($o['round'], 0, $this->engine->maxRounds() - 1, 'Manche');
        $pot = $this->guard->between($o['pot'], 0, self::MAX_POT, 'Cagnotte');
        if (!in_array($o['leader'], [GameEngine::PLAYER, GameEngine::AI], true)) {
            throw new InvalidArgumentException('Meneur inconnu.');
        }
        $seed = $o['seed'];
        $playerTop = $this->guard->cardIds($o['player_top']);
        $aiTop = $this->guard->cardIds($o['ai_top']);
        if (count($playerTop) > $playerCount || count($aiTop) > $aiCount) {
            throw new DomainException('Plus de cartes imposées que de cartes dans la pile.');
        }

        if ($seed !== null) {
            mt_srand($seed);
        }
        $pool = array_keys($this->cards);
        shuffle($pool);
        $draw = $this->dealer($pool, array_values(array_diff($pool, $playerTop, $aiTop)));

        $state = $this->engine->start(
            $this->fill($playerTop, $playerCount, $draw),
            $this->fill($aiTop, $aiCount, $draw),
            $o['difficulty'],
            $this->abilities->initial(),
        );
        $state['round'] = $round;
        $state['leader'] = $o['leader'];
        $state['pot'] = $this->fill([], $pot, $draw);
        if ($seed !== null) {
            $state['seed'] = $seed;
            mt_srand($seed + $round);
        }

        return $o['leader'] === GameEngine::AI ? $this->engine->announce($state) : $state;
    }

    /**
     * Le plan d'un scénario de fin : la partie est à une manche de la fin, et les cartes en main décident du résultat.
     *
     * @return array{player_count: int, ai_count: int, round: int, leader: string, player_top: int[], ai_top: int[]}
     */
    public function scenario(string $name): array
    {
        ['strong' => $strong, 'weak' => $weak] = $this->dominantHands();
        $last = $this->engine->maxRounds() - 1; // une seule manche reste à jouer
        // [cartes joueur, cartes ordinateur, manche déjà jouée, main du joueur, main de l'ordinateur] ; le gagnant de la dernière manche est celui qui a la main « strong »
        [$playerCount, $aiCount, $round, $playerTop, $aiTop] = match ($name) {
            'ko_win'     => [8, 1, 4, $strong, [$weak[0]]],
            'ko_lose'    => [1, 8, 4, [$weak[0]], $strong],
            'limit_win'  => [15, 5, $last, $weak, $strong],     // 15-5, il perd la dernière manche : 14-6, il gagne quand même
            'limit_lose' => [5, 15, $last, $strong, $weak],     // 5-15, il gagne la dernière manche : 6-14, il perd quand même
            'limit_draw' => [11, 9, $last, $weak, $strong],     // 11-9, il perd la dernière manche : 10-10
            default      => throw new InvalidArgumentException('Scénario inconnu.'),
        };

        return [
            'player_count' => $playerCount,
            'ai_count'     => $aiCount,
            'round'        => $round,
            'leader'       => $round % 2 === 0 ? GameEngine::PLAYER : GameEngine::AI, // même règle que le moteur
            'player_top'   => $playerTop,
            'ai_top'       => $aiTop,
        ];
    }

    /**
     * Tire dans les cartes libres ; s'il en faut plus que les cartes du jeu, des exemplaires se répètent.
     *
     * @param int[] $pool
     * @param int[] $free
     */
    private function dealer(array $pool, array $free): \Closure
    {
        $cycle = 0;

        return function () use (&$free, &$cycle, $pool): int {
            return array_shift($free) ?? $pool[$cycle++ % count($pool)];
        };
    }

    /**
     * @param int[] $top cartes imposées, placées en tête
     * @return int[]
     */
    private function fill(array $top, int $count, callable $draw): array
    {
        $pile = $top;
        while (count($pile) < $count) {
            $pile[] = $draw();
        }

        return $pile;
    }

    /**
     * Deux cartes « fortes » qui battent deux cartes « faibles » sur tous les attributs, quel que soit celui annoncé.
     *
     * @return array{strong: int[], weak: int[]}
     */
    private function dominantHands(): array
    {
        $keys = array_keys(Config::get('game.attributes'));
        $beats = fn (int $a, int $b): bool => array_reduce($keys, fn (bool $all, string $key): bool => $all && $this->cards[$a][$key] > $this->cards[$b][$key], true);
        $total = fn (int $id): int => array_sum(array_intersect_key($this->cards[$id], array_flip($keys)));

        $beaten = [];
        foreach (array_keys($this->cards) as $a) {
            foreach (array_keys($this->cards) as $b) {
                if ($a !== $b && $beats($a, $b)) {
                    $beaten[$a][] = $b;
                }
            }
        }
        $best = null;
        foreach ($beaten as $a => $listA) {
            foreach ($beaten as $b => $listB) {
                $common = array_values(array_intersect($listA, $listB));
                if ($a < $b && count($common) >= 2 && ($best === null || count($common) > count($best[2]))) {
                    $best = [$a, $b, $common];
                }
            }
        }
        if ($best === null) {
            throw new DomainException('Aucune paire de cartes ne domine les autres sur tous les attributs.');
        }
        usort($best[2], fn (int $x, int $y): int => $total($x) <=> $total($y));

        return ['strong' => [$best[0], $best[1]], 'weak' => [$best[2][0], $best[2][1]]];
    }
}
