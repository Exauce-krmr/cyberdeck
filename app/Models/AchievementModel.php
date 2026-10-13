<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Les succès débloqués par chaque joueur (leur définition est dans config/achievements.php). */
final class AchievementModel
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, string> code du succès => date de déblocage */
    public function unlocked(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT code, unlocked_at FROM achievements WHERE user_id = ? ORDER BY unlocked_at, code');
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Sans effet si le succès est déjà débloqué. */
    public function unlock(int $userId, string $code): void
    {
        if (array_key_exists($code, $this->unlocked($userId))) {
            return;
        }

        $this->pdo->prepare('INSERT INTO achievements (user_id, code) VALUES (?, ?)')->execute([$userId, $code]);
    }

    /** @return array<int, string[]> id du joueur => codes de ses succès (pour afficher les titres dans le classement) */
    public function unlockedByUser(): array
    {
        $byUser = [];
        foreach ($this->pdo->query('SELECT user_id, code FROM achievements')->fetchAll(PDO::FETCH_NUM) as [$userId, $code]) {
            $byUser[(int) $userId][] = $code;
        }

        return $byUser;
    }

    /** Outil de développement (Labo) : retire tous les succès d'un joueur. */
    public function clear(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM achievements WHERE user_id = ?')->execute([$userId]);
    }
}
