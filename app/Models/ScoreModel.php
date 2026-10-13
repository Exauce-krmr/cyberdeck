<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class ScoreModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(int $userId, string $difficulty, string $result, int $rounds): void
    {
        $this->pdo
            ->prepare('INSERT INTO scores (user_id, difficulty, result, rounds) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $difficulty, $result, $rounds]);
    }

    /** @return array<int, array{difficulty: string, result: string}> toutes les parties du joueur, de la plus ancienne à la plus récente */
    public function history(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT difficulty, result FROM scores WHERE user_id = ? ORDER BY id');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * Classement : le plus de victoires d'abord ; à victoires égales, celui qui en a le plus avec le moins de parties.
     * Chaque joueur porte aussi son détail par difficulté.
     *
     * @return list<array{id: int, username: string, title: string|null, games: int, wins: int, rate: int, by_difficulty: array<string, array{games: int, wins: int}>}>
     */
    public function leaderboard(int $limit): array
    {
        $rows = $this->pdo->query(
            'SELECT u.id, u.username, u.title, s.difficulty, s.result, COUNT(*) AS n
             FROM scores s JOIN users u ON u.id = s.user_id
             GROUP BY u.id, u.username, u.title, s.difficulty, s.result'
        )->fetchAll();

        $players = [];
        foreach ($rows as $row) {
            $name = $row['username'];
            $count = (int) $row['n'];
            $won = $row['result'] === 'win' ? $count : 0;
            $player = $players[$name] ?? ['id' => (int) $row['id'], 'username' => $name, 'title' => $row['title'], 'games' => 0, 'wins' => 0, 'rate' => 0, 'by_difficulty' => []];
            $level = $player['by_difficulty'][$row['difficulty']] ?? ['games' => 0, 'wins' => 0];

            $player['by_difficulty'][$row['difficulty']] = ['games' => $level['games'] + $count, 'wins' => $level['wins'] + $won];
            $player['games'] += $count;
            $player['wins'] += $won;
            $player['rate'] = (int) round(100 * $player['wins'] / $player['games']);
            $players[$name] = $player;
        }

        usort($players, fn (array $a, array $b) => [$b['wins'], $a['games']] <=> [$a['wins'], $b['games']]);

        return array_slice($players, 0, $limit);
    }

    /** @return array{games: int, wins: int, rate: int, by_difficulty: array<string, array{games: int, wins: int}>} */
    public function statsFor(string $username): array
    {
        foreach ($this->leaderboard(PHP_INT_MAX) as $row) {
            if ($row['username'] === $username) {
                return ['games' => $row['games'], 'wins' => $row['wins'], 'rate' => $row['rate'], 'by_difficulty' => $row['by_difficulty']];
            }
        }

        return ['games' => 0, 'wins' => 0, 'rate' => 0, 'by_difficulty' => []];
    }

    /** Outil de développement (Labo) : efface l'historique de parties d'un joueur. */
    public function clear(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM scores WHERE user_id = ?')->execute([$userId]);
    }
}
