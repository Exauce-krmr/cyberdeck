<?php

declare(strict_types=1);

namespace App\Services\Dev;

use InvalidArgumentException;

/** Validation des entrées du Labo : un seul endroit pour les messages d'erreur et les bornes. */
final class DevGuard
{
    /**
     * @param string[] $difficulties
     * @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id
     */
    public function __construct(private array $difficulties, private array $cards)
    {
    }

    public function difficulty(string $difficulty): void
    {
        if (!in_array($difficulty, $this->difficulties, true)) {
            throw new InvalidArgumentException('Difficulté inconnue.');
        }
    }

    public function between(int $value, int $min, int $max, string $label): int
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("{$label} : entre {$min} et {$max}.");
        }

        return $value;
    }

    /**
     * @param int[] $ids
     * @return int[] les mêmes ids, vérifiés (existent, sans doublon)
     */
    public function cardIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        foreach ($ids as $id) {
            if (!isset($this->cards[$id])) {
                throw new InvalidArgumentException("Carte inconnue : {$id}.");
            }
        }

        return $ids;
    }
}
