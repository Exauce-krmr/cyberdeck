<?php

declare(strict_types=1);

namespace App\Services\Game;

/** Ce que le navigateur a le droit de voir d'une partie : jamais la carte de l'ordinateur avant la fin de la manche. */
final class GameView
{
    /** @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id */
    public function __construct(
        private GameEngine $engine,
        private GameSummary $summary,
        private array $cards,
    ) {
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function of(array $state): array
    {
        $playing = $state['status'] === 'playing';
        $known = $playing ? $this->knownCards($state) : [];

        return [
            'status'       => $state['status'],
            'difficulty'   => $state['difficulty'],
            'round'        => $state['round'],
            'max_rounds'   => $this->engine->maxRounds(),
            'leader'       => $state['leader'],
            'player_count' => count($state['player']),
            'ai_count'     => count($state['ai']),
            'pot'          => count($state['pot']),
            'player_hand'  => $playing ? $this->cardsOf(array_slice($state['player'], 0, $this->engine->handSize())) : [],
            'announced'    => $state['announced']['attribute'] ?? null, // l'attribut seulement : la carte adverse reste secrète
            'abilities'    => $state['abilities'],
            'boost'        => $state['boost'],
            'scan'         => $state['scan'],
            // Cartes de l'ordinateur que le joueur a déjà vues (sans leur ordre) et nombre de celles qu'il n'a jamais vues.
            'player_pile'  => $playing ? $this->cardsOf(array_slice($state['player'], $this->engine->handSize())) : [],
            'ai_known'     => $known,
            'ai_unknown'   => $playing ? count($state['ai']) - count($known) : 0,
            'summary'      => $playing ? null : $this->summary->of($state),
            'unlocked'     => $state['unlocked'] ?? ['achievements' => [], 'titles' => []],
            'reward'       => $state['reward'] ?? null, // booster gagné (voir Boosters::awardForWin)
        ];
    }

    /**
     * Les cartes de la pile adverse déjà vues, triées par id : leur position reste secrète.
     *
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>
     */
    private function knownCards(array $state): array
    {
        $ids = $state['known'];
        sort($ids);

        return $this->cardsOf($ids);
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    private function cardsOf(array $ids): array
    {
        return array_map(fn (int $id): array => $this->cards[$id], $ids);
    }
}
