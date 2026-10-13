<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class UserModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $username, string $passwordHash): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, $passwordHash]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    /** Titre affiché par le joueur ; null = le plus haut qu'il a débloqué. */
    public function setTitle(int $id, ?string $title): void
    {
        $this->pdo->prepare('UPDATE users SET title = ? WHERE id = ?')->execute([$title, $id]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function shinyPoints(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT shiny_points FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function addShinyPoints(int $id, int $points): void
    {
        $this->pdo->prepare('UPDATE users SET shiny_points = shiny_points + ? WHERE id = ?')->execute([$points, $id]);
    }

    /** Dépense les points d'un seul coup ; faux (et rien de retiré) s'il n'y en a pas assez. */
    public function spendShinyPoints(int $id, int $points): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET shiny_points = shiny_points - ? WHERE id = ? AND shiny_points >= ?');
        $stmt->execute([$points, $id, $points]);

        return $stmt->rowCount() === 1;
    }

    /** Outil de développement (Labo) : fixe le solde de points shiny. */
    public function setShinyPoints(int $id, int $points): void
    {
        $this->pdo->prepare('UPDATE users SET shiny_points = ? WHERE id = ?')->execute([max(0, $points), $id]);
    }

    /** @return string[] pseudos commençant par $prefix (comptes de test du Labo) */
    public function usernamesWithPrefix(string $prefix): array
    {
        // SUBSTR plutôt que LIKE : pas de caractères joker (« _ » en est un) à échapper, et même syntaxe en MariaDB et en SQLite.
        $stmt = $this->pdo->prepare('SELECT username FROM users WHERE SUBSTR(username, 1, ?) = ? ORDER BY username');
        $stmt->execute([strlen($prefix), $prefix]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Les lignes liées (cartes, scores, succès, boosters) partent avec lui (ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
