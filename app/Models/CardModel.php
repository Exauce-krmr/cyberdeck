<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class CardModel
{
    /** @param string[] $attributeKeys colonnes numériques à convertir en entiers */
    public function __construct(private PDO $pdo, private array $attributeKeys)
    {
    }

    /** @return array<int, array<string, mixed>> toutes les cartes, indexées par id */
    public function all(): array
    {
        $cards = [];
        foreach ($this->pdo->query('SELECT * FROM cards ORDER BY id')->fetchAll() as $row) {
            $row['id'] = (int) $row['id'];
            foreach ($this->attributeKeys as $key) {
                $row[$key] = (int) $row[$key];
            }
            $cards[$row['id']] = $row;
        }

        return $cards;
    }

    /** @return int[] */
    public function idsByRarity(string $rarity): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM cards WHERE rarity = ?');
        $stmt->execute([$rarity]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
