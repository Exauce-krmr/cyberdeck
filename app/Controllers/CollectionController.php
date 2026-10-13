<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\View;
use App\Models\CardModel;
use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Services\Auth\AuthService;

final class CollectionController
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private CardModel $cards,
        private CollectionModel $collection,
        private ScoreModel $scores,
    ) {
    }

    public function index(): void
    {
        $user = $this->auth->user();
        $owned = array_flip($this->collection->cardIds((int) $user['id']));

        Http::html($this->view->render('collection.index', [
            'cards' => $this->cards->all(),
            'owned' => $owned,
            'shinyUnlocked' => array_flip($this->collection->shinyIds((int) $user['id'])),
            'shinyOn' => array_flip($this->collection->equippedShinyIds((int) $user['id'])),
            'stats' => $this->scores->statsFor($user['username']),
        ]));
    }

    /** Équipe ou retire le skin shiny d'une carte (seulement s'il est débloqué). */
    public function toggleShiny(): void
    {
        $userId = $this->auth->userId();
        $cardId = (int) (Http::input()['card'] ?? 0);

        if ($userId === null || !$this->collection->toggleShiny($userId, $cardId)) {
            Http::json(['error' => "Ce skin n'est pas débloqué."], 422);

            return;
        }
        Http::json(['on' => in_array($cardId, $this->collection->equippedShinyIds($userId), true)]);
    }
}
