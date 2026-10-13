<?php

declare(strict_types=1);

namespace App\Services\Achievements;

/**
 * Transforme l'historique d'un joueur et sa dernière partie en mesures numériques (voir config/achievements.php).
 * Une mesure vaut null quand elle n'a pas de sens : par exemple, « manches d'une victoire » après une défaite.
 */
final class AchievementMetrics
{
    /**
     * @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id
     * @param string $hardest difficulté la plus haute
     * @param string $lowestRarity rareté de base (« commune »)
     * @param string $topRarity rareté la plus haute (« légendaire »)
     */
    public function __construct(
        private array $cards,
        private string $hardest,
        private string $lowestRarity,
        private string $topRarity,
    ) {
    }

    /**
     * @param list<array{difficulty: string, result: string}> $history parties terminées, de la plus ancienne à la plus récente
     * @param int[] $ownedIds cartes possédées
     * @param int[] $shinyIds cartes dont le skin shiny est débloqué
     * @param array{status: string, round: int, deck: int[], abilities_used: array<string, int>, summary: array<string, mixed>}|null $game la partie qui vient de se terminer
     * @return array<string, int|null>
     */
    public function compute(array $history, array $ownedIds, ?array $game, array $shinyIds = []): array
    {
        $results = array_column($history, 'result');
        $owned = array_intersect_key($this->cards, array_flip($ownedIds));
        $ownedRarities = array_count_values(array_column($owned, 'rarity'));

        $measures = [
            'games'            => count($history),
            'wins'             => count(array_filter($results, fn (string $r): bool => $r === 'win')),
            'wins_hardest'     => count(array_filter($history, fn (array $g): bool => $g['result'] === 'win' && $g['difficulty'] === $this->hardest)),
            'win_streak'       => $this->trailing($results, 'win'),
            'lose_streak'      => $this->trailing($results, 'lose'),
            'cards_owned'      => count($owned),
            'cards_missing'    => count($this->cards) - count($owned),
            'shiny_missing'    => count($this->cards) - count(array_intersect_key($this->cards, array_flip($shinyIds))),
            'rare_owned'       => count($owned) - ($ownedRarities[$this->lowestRarity] ?? 0),
            'legendary_owned'  => $ownedRarities[$this->topRarity] ?? 0,
        ];

        return $measures + $this->gameMeasures($game);
    }

    /**
     * @param array{status: string, round: int, deck: int[], abilities_used: array<string, int>, summary: array<string, mixed>}|null $game
     * @return array<string, int|null>
     */
    private function gameMeasures(?array $game): array
    {
        $won = $game !== null && $game['status'] === 'win';
        $deckRarities = $game === null ? [] : array_count_values(array_map(fn (int $id): string => $this->cards[$id]['rarity'], $game['deck']));
        $used = $game['abilities_used'] ?? [];

        return [
            'scans_used'            => $game === null ? null : ($used['scan'] ?? 0),
            'abilities_used'        => $game === null ? null : count(array_filter($used)),
            'win_rounds'            => $won ? $game['round'] : null,
            'win_rounds_lost'       => $won ? $game['summary']['lost'] : null,
            'win_comeback_depth'    => $won ? $game['summary']['deficit'] : null,
            'win_abilities_used'    => $won ? array_sum($used) : null,
            'win_deck_above_common' => $won ? count($game['deck']) - ($deckRarities[$this->lowestRarity] ?? 0) : null,
            'win_deck_legendaries'  => $won ? ($deckRarities[$this->topRarity] ?? 0) : null,
        ];
    }

    /**
     * Nombre de résultats identiques à la fin de la liste (« 3 victoires d'affilée »).
     *
     * @param string[] $results
     */
    private function trailing(array $results, string $wanted): int
    {
        $count = 0;
        foreach (array_reverse($results) as $result) {
            if ($result !== $wanted) {
                break;
            }
            $count++;
        }

        return $count;
    }
}
