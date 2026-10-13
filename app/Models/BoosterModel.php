<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Les boosters en stock de chaque joueur (un booster ouvert disparaît de la table). */
final class BoosterModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function add(int $userId, string $kind, string $difficulty): int
    {
        $this->pdo->prepare('INSERT INTO user_boosters (user_id, kind, difficulty) VALUES (?, ?, ?)')->execute([$userId, $kind, $difficulty]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int, array{id: int, kind: string, difficulty: string}> du plus ancien au plus récent */
    public function stock(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, kind, difficulty FROM user_boosters WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);

        return array_map(fn (array $row): array => ['id' => (int) $row['id'], 'kind' => $row['kind'], 'difficulty' => $row['difficulty']], $stmt->fetchAll());
    }

    /**
     * Retire le booster du stock et le renvoie ; null s'il n'existe pas ou n'est pas au joueur.
     * Un seul DELETE : deux clics rapides ne peuvent pas ouvrir deux fois le même booster.
     *
     * @return array{id: int, kind: string, difficulty: string}|null
     */
    public function take(int $userId, int $boosterId): ?array
    {
        $found = array_values(array_filter($this->stock($userId), fn (array $b): bool => $b['id'] === $boosterId))[0] ?? null;
        if ($found === null) {
            return null;
        }
        $stmt = $this->pdo->prepare('DELETE FROM user_boosters WHERE id = ? AND user_id = ?');
        $stmt->execute([$boosterId, $userId]);

        return $stmt->rowCount() === 1 ? $found : null;
    }

    /** Outil de développement (Labo) : vide le stock de boosters d'un joueur. */
    public function clear(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM user_boosters WHERE user_id = ?')->execute([$userId]);
    }
}
