<?php

declare(strict_types=1);

namespace App\Services\Game;

use DomainException;
use InvalidArgumentException;

/**
 * Règles du jeu, sans base de données ni session : l'état d'une partie est un simple tableau.
 *
 *  - chaque joueur a une pile de cartes ; les `handSize` du dessus forment sa main
 *  - le meneur joue une carte de sa main et annonce l'attribut ; l'autre répond avec la carte de sa main qu'il veut
 *  - la valeur la plus haute gagne ; le rôle de meneur alterne à chaque manche
 *  - le gagnant glisse les deux cartes sous sa pile (donc les cartes déjà vues reviennent : il faut s'en souvenir)
 *  - égalité : les deux cartes vont dans la cagnotte, gagnée par le prochain vainqueur
 *  - fin : un joueur n'a plus de cartes, ou la limite de manches est atteinte (le plus de cartes gagne)
 */
final class GameEngine
{
    public const PLAYER = 'player';
    public const AI = 'ai';
    /**
     * @param array<int, array<string, mixed>> $cards cartes indexées par id
     * @param string[] $attributeKeys
     */
    public function __construct(
        private array $cards,
        private array $attributeKeys,
        private AiPlayer $ai,
        private int $maxRounds,
        private int $handSize,
        private int $boostBonus = 15,
    ) {
    }

    public function maxRounds(): int
    {
        return $this->maxRounds;
    }

    public function handSize(): int
    {
        return $this->handSize;
    }

    /**
     * @param int[] $playerIds pile du joueur, carte du dessus en premier
     * @param int[] $aiIds pile de l'ordinateur
     * @param array<string, int> $abilities utilisations restantes de chaque capacité
     * @return array<string, mixed>
     */
    public function start(array $playerIds, array $aiIds, string $difficulty, array $abilities = []): array
    {
        return [
            'difficulty' => $difficulty,
            'player'     => array_values($playerIds),
            'ai'         => array_values($aiIds),
            'pot'        => [],
            'leader'     => self::PLAYER,
            'round'      => 0,
            'status'     => 'playing',
            'abilities'  => $abilities,
            'boost'      => false,           // un Boost est armé pour la prochaine manche
            'announced'  => null,            // quand l'ordinateur mène : ['index' => carte de sa main, 'attribute' => attribut annoncé]
            'scan'       => [],              // attribut => meilleure valeur révélée dans la main adverse
            'known'      => [],              // ids des cartes que le joueur sait dans la pile adverse (un id par exemplaire, ordre inconnu)
            'deck'       => $playerIds,      // le deck de départ du joueur (pour les succès liés à sa composition)
            'history'    => [],              // une entrée par manche jouée, pour le résumé de fin de partie
        ];
    }

    /**
     * Joue une manche avec la carte $handIndex de la main du joueur (0 = dessus de pile).
     * Si le joueur mène, $attribute est l'attribut qu'il annonce ; sinon c'est celui annoncé par l'ordinateur.
     *
     * @param array<string, mixed> $state
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [nouvel état, résultat de la manche]
     */
    public function play(array $state, int $handIndex, ?string $attribute): array
    {
        if ($state['status'] !== 'playing') {
            throw new DomainException('La partie est terminée.');
        }
        if ($handIndex < 0 || $handIndex >= min($this->handSize, count($state['player']))) {
            throw new InvalidArgumentException('Carte inconnue dans ta main.');
        }

        $chosenBy = $state['leader'];
        if ($chosenBy === self::AI) {
            ['index' => $aiIndex, 'attribute' => $attribute] = $state['announced'];
        } elseif (in_array($attribute, $this->attributeKeys, true)) {
            $aiIndex = $this->ai->answer($this->hand($state['ai']), $attribute, $state['difficulty']);
        } else {
            throw new InvalidArgumentException('Attribut inconnu.');
        }

        $playerId = array_splice($state['player'], $handIndex, 1)[0];
        $aiId = array_splice($state['ai'], $aiIndex, 1)[0];

        $boosted = $state['boost'];
        $playerValue = $this->cards[$playerId][$attribute] + ($boosted ? $this->boostBonus : 0);
        $aiValue = $this->cards[$aiId][$attribute];
        $state['round']++;
        $state['boost'] = false;
        $state['scan'] = [];
        $this->forget($state['known'], $aiId); // elle quitte la pile adverse
        $state['leader'] = $state['round'] % 2 === 0 ? self::PLAYER : self::AI;
        $state['announced'] = null;

        $state['history'][] = [
            'round'        => $state['round'],
            'attribute'    => $attribute,
            'player_card'  => $playerId,
            'ai_card'      => $aiId,
            'player_value' => $playerValue,
            'ai_value'     => $aiValue,
            'chosen_by'    => $chosenBy,
        ];

        $comparison = $playerValue <=> $aiValue;
        if ($comparison === 0) {
            $outcome = 'tie';
            $state['pot'] = [...$state['pot'], $playerId, $aiId];
        } else {
            $outcome = $comparison > 0 ? self::PLAYER : self::AI;
            $won = $outcome === self::PLAYER ? [$playerId, $aiId] : [$aiId, $playerId];
            $state[$outcome] = [...$state[$outcome], ...$won, ...$state['pot']];
            if ($outcome === self::AI) { // tout ce que l'ordinateur ramasse a été vu : on sait donc qu'il l'a
                $state['known'] = [...$state['known'], ...$won, ...$state['pot']];
            }
            $state['pot'] = [];
        }

        $state['status'] = $this->status($state);
        if ($state['status'] === 'playing' && $state['leader'] === self::AI) {
            $state = $this->announce($state);
        }

        return [$state, [
            'attribute'    => $attribute,
            'outcome'      => $outcome,
            'chosen_by'    => $chosenBy,
            'player_card'  => $this->cards[$playerId],
            'ai_card'      => $this->cards[$aiId],
            'player_value' => $playerValue,
            'ai_value'     => $aiValue,
            'boosted'      => $boosted,
            'pot'          => count($state['pot']),
        ]];
    }

    /**
     * L'ordinateur choisit sa carte et son attribut dès le début de la manche : le joueur ne voit que l'attribut.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function announce(array $state): array
    {
        [$index, $attribute] = $this->ai->lead($this->hand($state['ai']), $state['difficulty']);
        $state['announced'] = ['index' => $index, 'attribute' => $attribute];

        return $state;
    }

    /**
     * @param int[] $pile
     * @return array<int, array<string, mixed>> les cartes de la main (dessus de pile), dans l'ordre
     */
    private function hand(array $pile): array
    {
        return array_map(fn (int $id): array => $this->cards[$id], array_slice($pile, 0, $this->handSize));
    }

    /** @param array<string, mixed> $state */
    private function status(array $state): string
    {
        $mine = count($state['player']);
        $theirs = count($state['ai']);

        if ($mine > 0 && $theirs > 0 && $state['round'] < $this->maxRounds) {
            return 'playing';
        }

        return match ($mine <=> $theirs) {
            1       => 'win',
            -1      => 'lose',
            default => 'draw',
        };
    }

    /**
     * Retire un seul exemplaire de $id (deux cartes identiques peuvent exister quand une rareté manque).
     *
     * @param int[] $ids
     */
    private function forget(array &$ids, int $id): void
    {
        $position = array_search($id, $ids, true);
        if ($position !== false) {
            array_splice($ids, $position, 1);
        }
    }
}
