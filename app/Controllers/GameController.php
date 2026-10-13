<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Http;
use App\Core\View;
use App\Presenters\GamePresenter;
use App\Services\Auth\AuthService;
use App\Services\Game\GameService;
use DomainException;
use InvalidArgumentException;

final class GameController
{
    private ?GamePresenter $presenter = null;

    public function __construct(private View $view, private GameService $game, private AuthService $auth)
    {
    }

    public function page(): void
    {
        Http::html($this->view->render('game.play', [
            'difficulties' => Config::get('game.difficulties'),
            'abilities'    => Config::get('game.abilities'),
            'deckSize'     => Config::get('game.deck_size'),
            'deckLimits'   => Config::get('game.deck_limits'),
            'rarities'     => Config::get('game.rarities'),
            'ownedCards'   => $this->game->ownedCards($this->auth->requireUserId()),
        ]));
    }

    public function state(): void
    {
        $game = $this->game->current($this->auth->requireUserId());

        Http::json(['game' => $game === null ? null : $this->presenter()->game($game)]);
    }

    public function start(): void
    {
        $this->respond(function () {
            $input = Http::input();
            $deck = is_array($input['deck'] ?? null) ? $input['deck'] : [];

            return ['game' => $this->presenter()->game($this->game->start($this->auth->requireUserId(), (string) ($input['difficulty'] ?? ''), $deck))];
        });
    }

    public function ability(): void
    {
        $this->respond(function () {
            $input = Http::input();
            $attribute = $input['attribute'] ?? null;
            $game = $this->game->useAbility($this->auth->requireUserId(), (string) ($input['ability'] ?? ''), is_string($attribute) ? $attribute : null);

            return ['game' => $this->presenter()->game($game)];
        });
    }

    public function abandon(): void
    {
        $this->respond(function () {
            $this->game->abandon($this->auth->requireUserId());

            return ['game' => null];
        });
    }

    public function play(): void
    {
        $this->respond(function () {
            $input = Http::input();
            $attribute = $input['attribute'] ?? null;
            $played = $this->game->play($this->auth->requireUserId(), (int) ($input['hand'] ?? 0), is_string($attribute) ? $attribute : null);

            return [
                'result' => $this->presenter()->result($played['result']),
                'game'   => $this->presenter()->game($played['game']),
            ];
        });
    }

    /** Un présentateur par requête : les cartes shiny du joueur ne sont lues qu'une fois. */
    private function presenter(): GamePresenter
    {
        return $this->presenter ??= new GamePresenter($this->view, $this->game->equippedShinies($this->auth->requireUserId()));
    }

    /** Exécute l'action et transforme une erreur de règle en réponse 422 lisible par l'interface. */
    private function respond(callable $action): void
    {
        try {
            Http::json($action());
        } catch (DomainException | InvalidArgumentException $e) {
            Http::json(['error' => $e->getMessage()], 422);
        }
    }
}
