<?php

declare(strict_types=1);

namespace App\Services\Game;

/** Construit le paquet de l'ordinateur : mêmes raretés que celui du joueur, pour un duel équitable. */
final class AiDeck
{
    /** @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id */
    public function __construct(private array $cards)
    {
    }

    /**
     * Pour chaque carte du joueur, une carte de même rareté que le joueur n'a pas dans sa main.
     * S'il n'en reste plus de cette rareté, l'ordinateur reçoit un exemplaire de la carte du joueur.
     *
     * @param int[] $playerIds
     * @return int[]
     */
    public function matching(array $playerIds): array
    {
        $availableByRarity = [];
        foreach ($this->cards as $id => $card) {
            if (!in_array($id, $playerIds, true)) {
                $availableByRarity[$card['rarity']][] = $id;
            }
        }
        foreach ($availableByRarity as &$ids) {
            shuffle($ids);
        }
        unset($ids);

        $deck = [];
        foreach ($playerIds as $playerId) {
            $rarity = $this->cards[$playerId]['rarity'];
            $remaining = $availableByRarity[$rarity] ?? [];
            $deck[] = array_pop($remaining) ?? $playerId;
            $availableByRarity[$rarity] = $remaining;
        }

        shuffle($deck);

        return $deck;
    }
}
