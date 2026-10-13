<?php

declare(strict_types=1);

namespace App\Services\Game;

/** Bilan d'une partie terminée. Pur : ne dépend que de l'historique de la partie et du catalogue de cartes. */
final class GameSummary
{
    /** @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id */
    public function __construct(private array $cards)
    {
    }

    /**
     * Victoires par manche, carte la plus efficace, plus gros écart, et le détail de chaque manche.
     * La manche est gagnée par celui dont la valeur est la plus haute (égalité : aucune).
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function of(array $state): array
    {
        $won = $lost = $tied = 0;
        $balance = $deficit = 0; // manches gagnées moins manches perdues, et son point le plus bas
        $winsByCard = [];
        $biggest = null;
        $rounds = [];

        foreach ($state['history'] as $entry) {
            $margin = $entry['player_value'] <=> $entry['ai_value'];
            $gap = $entry['player_value'] - $entry['ai_value'];
            $balance += $margin;
            $deficit = max($deficit, -$balance);
            match (true) {
                $margin > 0 => $won++,
                $margin < 0 => $lost++,
                default => $tied++,
            };
            if ($margin > 0) {
                $winsByCard[$entry['player_card']] = ($winsByCard[$entry['player_card']] ?? 0) + 1;
                if ($biggest === null || $gap > $biggest['gap']) {
                    $biggest = ['round' => $entry['round'], 'gap' => $gap, 'card' => $this->name($entry['player_card']), 'attribute' => $entry['attribute']];
                }
            }
            $rounds[] = [
                'round'        => $entry['round'],
                'attribute'    => $entry['attribute'],
                'outcome'      => $margin > 0 ? GameEngine::PLAYER : ($margin < 0 ? GameEngine::AI : 'tie'),
                'chosen_by'    => $entry['chosen_by'],
                'player_name'  => $this->name($entry['player_card']),
                'ai_name'      => $this->name($entry['ai_card']),
                'player_value' => $entry['player_value'],
                'ai_value'     => $entry['ai_value'],
            ];
        }

        arsort($winsByCard);
        $bestId = array_key_first($winsByCard);

        return [
            'won'     => $won,
            'lost'    => $lost,
            'tied'    => $tied,
            'deficit' => $deficit,
            'best'    => $bestId === null ? null : ['name' => $this->name($bestId), 'wins' => $winsByCard[$bestId]],
            'biggest' => $biggest,
            'rounds'  => $rounds,
        ];
    }

    private function name(int $cardId): string
    {
        return $this->cards[$cardId]['name'];
    }
}
