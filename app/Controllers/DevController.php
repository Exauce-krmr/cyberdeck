<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Http;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth\AuthService;
use App\Services\Dev\DevAccess;
use App\Services\Dev\DevTools;
use DomainException;
use InvalidArgumentException;

/**
 * Le Labo : une page et une seule route d'action. L'accès est vérifié ici à chaque requête (DevAccess), jamais seulement dans le menu :
 * sans autorisation la page répond 404, comme si elle n'existait pas.
 */
final class DevController
{
    private const FLASH_KEY = 'dev_flash';

    /** @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id */
    public function __construct(
        private View $view,
        private AuthService $auth,
        private DevAccess $access,
        private DevTools $tools,
        private array $cards,
    ) {
    }

    public function index(): void
    {
        if (!$this->allowed()) {
            $this->notFound();

            return;
        }

        $flash = Session::get(self::FLASH_KEY);
        Session::set(self::FLASH_KEY, null); // affiché une seule fois

        Http::html($this->view->render('dev.index', [
            'flash'        => is_array($flash) ? $flash : null,
            'snapshot'     => $this->tools->snapshot($this->auth->requireUserId()),
            'cardList'     => array_map(fn (array $c): array => ['id' => $c['id'], 'name' => $c['name'], 'rarity' => $c['rarity']], array_values($this->cards)),
            'difficulties' => Config::get('game.difficulties'),
            'maxRounds'    => Config::get('game.max_rounds'),
            'handSize'     => Config::get('game.hand_size'),
            'presets'      => DevTools::PRESETS,
            'scenarios'    => DevTools::SCENARIOS,
            'testAccounts' => $this->tools->testAccounts(),
            'testPassword' => Config::get('app.dev.test_password'),
        ]));
    }

    public function act(): void
    {
        if (!$this->allowed()) {
            $this->notFound();

            return;
        }

        $in = Http::input();
        $userId = $this->auth->requireUserId();
        $int = fn (string $key, int $default = 0): int => (int) ($in[$key] ?? $default);
        $text = fn (string $key): string => trim((string) ($in[$key] ?? ''));
        // Cartes imposées : une liste de <select> (champ « nom[] »). Une case vide veut dire « au hasard » et disparaît.
        $ids = fn (string $key): array => array_values(array_filter(array_map('intval', (array) ($in[$key] ?? [])), fn (int $id): bool => $id > 0));

        try {
            // Boosters, cartes et pièces peuvent viser un compte de test (champ « target » ; vide = ton compte).
            $target = $this->tools->targetId($userId, $text('target'));
            $message = match ($text('action')) {
                'give_boosters'  => $this->tools->giveBoosters($target, $text('kind'), $text('difficulty'), $int('count', 1)),
                'clear_boosters' => $this->tools->clearBoosters($target),
                'shiny_points'   => $this->tools->setShinyPoints($target, $int('points')),
                'collection'     => $this->tools->collection($target, $text('mode'), $int('missing')),
                'give_cards'     => $this->tools->giveCards($target, $ids('cards')),
                'remove_cards'   => $this->tools->removeCards($target, $ids('cards')),
                'shinies'        => $this->tools->shinies($target, $text('mode') === 'on'),
                'setup_game'     => $this->tools->setupGame($userId, [
                    'difficulty'   => $text('difficulty'),
                    'player_count' => $int('player_count', 10),
                    'ai_count'     => $int('ai_count', 10),
                    'round'        => $int('round'),
                    'leader'       => $text('leader'),
                    'pot'          => $int('pot'),
                    'seed'         => $text('seed') === '' ? null : $int('seed'),
                    'player_top'   => $ids('player_top'),
                    'ai_top'       => $ids('ai_top'),
                ]),
                'scenario'       => $this->tools->scenario($userId, $text('scenario'), $text('difficulty')),
                'finish_game'    => $this->tools->finishGame($userId, $text('status'), $text('difficulty')),
                'fake_games'     => $this->tools->fakeGames($userId, $int('wins'), $int('losses'), $text('difficulty')),
                'clear_scores'   => $this->tools->clearScores($userId),
                'achievements'   => $this->tools->achievements($userId, $text('mode') === 'unlock'),
                'create_account' => $this->tools->createTestAccount($text('name'), $text('preset')),
                'delete_account' => $this->tools->deleteTestAccount($text('username')),
                'delete_all'     => $this->tools->deleteAllTestAccounts(),
                default          => throw new InvalidArgumentException('Action inconnue.'),
            };
            if ($text('action') === 'scenario') { // on va directement jouer la manche : rien à lire sur le Labo
                Http::redirect('/game');

                return;
            }
            Session::set(self::FLASH_KEY, ['ok' => true, 'text' => $text('target') === '' ? $message : "{$message} (compte {$text('target')})"]);
        } catch (DomainException | InvalidArgumentException $e) {
            Session::set(self::FLASH_KEY, ['ok' => false, 'text' => $e->getMessage()]);
        }

        Http::redirect('/dev');
    }

    private function allowed(): bool
    {
        return $this->access->allows($this->auth->username());
    }

    private function notFound(): void
    {
        Http::html('Page introuvable.', 404);
    }
}
