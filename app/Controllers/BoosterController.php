<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Http;
use App\Core\View;
use App\Models\CollectionModel;
use App\Services\Achievements\AchievementService;
use App\Services\Auth\AuthService;
use App\Services\Boosters\Boosters;
use DomainException;

/** L'onglet Boosters : stock, ouverture, achat d'un booster shiny avec les points. */
final class BoosterController
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private Boosters $boosters,
        private CollectionModel $collection,
        private AchievementService $achievements,
    ) {
    }

    public function index(): void
    {
        Http::html($this->view->render('boosters.index', [
            'overview'     => $this->boosters->overview($this->auth->requireUserId()),
            'difficulties' => Config::get('game.difficulties'),
            'content'      => Config::get('game.boosters'),
            'shiny'        => Config::get('game.shiny'),
        ]));
    }

    public function open(): void
    {
        $this->respond(function (): array {
            $userId = $this->auth->requireUserId();
            $opened = $this->boosters->open($userId, (int) (Http::input()['id'] ?? 0));
            $shinyIds = $this->collection->equippedShinyIds($userId);
            // Un skin shiny de plus peut compléter la collection : le succès (et son titre) se débloque ici, pas seulement après une partie.
            $unlocked = $opened['shiny'] ? $this->achievements->afterCollectionChange($userId) : ['achievements' => [], 'titles' => []];

            return [
                'opened'   => [
                    'kind'       => $opened['kind'],
                    'difficulty' => $opened['difficulty'],
                    'shiny'      => $opened['shiny'],
                    'points'     => $opened['points'],
                    'converted'  => $opened['converted'],
                    'cards_html' => array_map(
                        fn (array $card): string => $this->view->render('partials.card', ['card' => $card, 'shiny' => $opened['shiny'] || in_array($card['id'], $shinyIds, true)]),
                        $opened['cards'],
                    ),
                    'rarities'   => array_column($opened['cards'], 'rarity'),
                ],
                'overview' => $this->boosters->overview($userId),
                'unlocked' => $unlocked,
            ];
        });
    }

    public function buyShiny(): void
    {
        $this->respond(function (): array {
            $userId = $this->auth->requireUserId();
            $this->boosters->buyShiny($userId);

            return ['overview' => $this->boosters->overview($userId)];
        });
    }

    private function respond(callable $action): void
    {
        try {
            Http::json($action());
        } catch (DomainException $e) {
            Http::json(['error' => $e->getMessage()], 422);
        }
    }
}
