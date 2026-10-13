<?php

declare(strict_types=1);

namespace App\Services\Boosters;

use Closure;

/** Carte offerte au joueur qui gagne une partie : une carte qu'il n'a pas encore, tirée selon la rareté. */
final class Rewards
{
    /**
     * @param array<string, int> $weightByRarity chance de tirage d'une carte, selon sa rareté
     * @param (Closure(int): int)|null $roll tirage entre 1 et le maximum donné (remplaçable dans les tests)
     */
    public function __construct(private array $weightByRarity, private ?Closure $roll = null)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $cards toutes les cartes
     * @param int[] $ownedIds cartes déjà possédées
     * @param array<string, float> $boost multiplicateur de chance par rareté (1 si absente) : un booster peut favoriser ou freiner une rareté
     * @return int|null id de la carte gagnée, null si le joueur a déjà tout
     */
    public function pick(array $cards, array $ownedIds, array $boost = []): ?int
    {
        $pool = array_filter($cards, fn (array $card): bool => !in_array($card['id'], $ownedIds, true));
        if ($pool === []) {
            return null;
        }

        // Poids en centièmes : un multiplicateur décimal (0.5, 2.5) reste exact avec des tirages entiers.
        $weights = array_map(fn (array $card): int => max(1, (int) round(($this->weightByRarity[$card['rarity']] ?? 1) * 100 * ($boost[$card['rarity']] ?? 1))), $pool);
        $roll = ($this->roll ?? fn (int $max): int => random_int(1, $max))(array_sum($weights));

        foreach ($pool as $id => $card) {
            $roll -= $weights[$id];
            if ($roll <= 0) {
                return (int) $id;
            }
        }

        return null;
    }
}
