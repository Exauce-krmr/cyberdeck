<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/** Les cartes possédées par chaque joueur. */
final class CollectionModel
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return int[] */
    public function cardIds(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT card_id FROM user_cards WHERE user_id = ?');
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Sans effet si le joueur possède déjà la carte. */
    public function add(int $userId, int $cardId): void
    {
        if (in_array($cardId, $this->cardIds($userId), true)) {
            return;
        }

        $this->pdo->prepare('INSERT INTO user_cards (user_id, card_id) VALUES (?, ?)')->execute([$userId, $cardId]);
    }

    /** Outil de développement (Labo) : retire une carte (et son skin shiny) du joueur. */
    public function remove(int $userId, int $cardId): void
    {
        $this->pdo->prepare('DELETE FROM user_cards WHERE user_id = ? AND card_id = ?')->execute([$userId, $cardId]);
    }

    /** @return int[] cartes dont le skin shiny est débloqué */
    public function shinyIds(int $userId): array
    {
        return $this->idsWhere($userId, 'shiny = 1');
    }

    /** @return int[] cartes dont le skin shiny est équipé */
    public function equippedShinyIds(int $userId): array
    {
        return $this->idsWhere($userId, 'shiny_on = 1');
    }

    /** Débloque le skin et l'équipe tout de suite. */
    public function unlockShiny(int $userId, int $cardId): void
    {
        $this->pdo->prepare('UPDATE user_cards SET shiny = 1, shiny_on = 1 WHERE user_id = ? AND card_id = ?')->execute([$userId, $cardId]);
    }

    /** Équipe ou retire le skin d'une carte ; faux si le joueur ne l'a pas débloqué. */
    public function toggleShiny(int $userId, int $cardId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_cards SET shiny_on = 1 - shiny_on WHERE user_id = ? AND card_id = ? AND shiny = 1');
        $stmt->execute([$userId, $cardId]);

        return $stmt->rowCount() === 1;
    }

    /** @return int[] */
    private function idsWhere(int $userId, string $condition): array
    {
        $stmt = $this->pdo->prepare("SELECT card_id FROM user_cards WHERE user_id = ? AND {$condition}");
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Outil de développement (Labo) : retire toutes les cartes du joueur. */
    public function clear(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM user_cards WHERE user_id = ?')->execute([$userId]);
    }

    /** Outil de développement (Labo) : débloque (et équipe) ou retire le skin shiny de toutes les cartes possédées. */
    public function setAllShiny(int $userId, bool $on): void
    {
        $this->pdo->prepare('UPDATE user_cards SET shiny = ?, shiny_on = ? WHERE user_id = ?')->execute([(int) $on, (int) $on, $userId]);
    }
}
