<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\BoosterController;
use App\Controllers\CollectionController;
use App\Controllers\DevController;
use App\Controllers\GameController;
use App\Controllers\LeaderboardController;
use App\Controllers\ProfileController;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\View;
use App\Models\AchievementModel;
use App\Models\BoosterModel;
use App\Models\CardModel;
use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Models\UserModel;
use App\Services\Game\Abilities;
use App\Services\Achievements\AchievementMetrics;
use App\Services\Achievements\AchievementService;
use App\Services\Game\AiDeck;
use App\Services\Game\AiPlayer;
use App\Services\Auth\AuthService;
use App\Services\Dev\DevAccess;
use App\Services\Dev\DevTools;
use App\Services\Boosters\Boosters;
use App\Services\Game\GameEngine;
use App\Services\Game\GameService;
use App\Services\Boosters\Rewards;

// Assemble les objets : c'est le seul fichier qui sait comment ils s'emboîtent.
// La connexion est fournie de l'extérieur : MariaDB en production, SQLite dans les tests.
return function (PDO $pdo): array {
    $attributes = Config::get('game.attributes');
    $attributeKeys = array_keys($attributes);
    $rarities = Config::get('game.rarities');
    $difficulties = Config::get('game.difficulties');

    $cardModel = new CardModel($pdo, $attributeKeys);
    $collection = new CollectionModel($pdo);
    $scores = new ScoreModel($pdo);
    $cards = $cardModel->all();

    $users = new UserModel($pdo);
    $auth = new AuthService($users, $collection, $cardModel, Config::get('game.starter_cards'), new BoosterModel($pdo), Config::get('game.welcome_boosters'));

    $devAccess = new DevAccess((bool) Config::get('app.dev.enabled'), Config::get('app.dev.users'));

    $view = new View(
        BASE_PATH . '/app/Views',
        BASE_PATH . '/storage/cache',
        fn (): array => [
            'csrf'       => Csrf::token(),
            'username'   => $auth->username(),
            'devAccess'  => $devAccess->allows($auth->username()),
            'attributes' => $attributes,
            'rarities'   => $rarities,
        ],
    );

    $engine = new GameEngine($cards, $attributeKeys, new AiPlayer($cards, $attributeKeys), Config::get('game.max_rounds'), Config::get('game.hand_size'), Config::get('game.boost_bonus'));
    $rewards = new Rewards(array_map(fn (array $r): int => $r['weight'], $rarities));
    $abilities = new Abilities(array_map(fn (array $a): int => $a['uses'], Config::get('game.abilities')), $cards, $attributeKeys, Config::get('game.hand_size'));
    $rarityKeys = array_keys($rarities);
    $achievements = new AchievementService(
        new AchievementModel($pdo),
        $scores,
        $collection,
        $users,
        new AchievementMetrics($cards, array_key_last($difficulties), $rarityKeys[0], $rarityKeys[array_key_last($rarityKeys)]),
        Config::get('achievements.list'),
        Config::get('achievements.titles'),
        Config::get('achievements.categories'),
    );
    $boosters = new Boosters(new BoosterModel($pdo), $collection, $users, $rewards, $cards, Config::get('game.boosters'), Config::get('game.shiny'));
    $game = new GameService($engine, $collection, $scores, $boosters, new AiDeck($cards), $abilities, $cards, Config::get('game.deck_size'), array_keys($difficulties), $achievements, Config::get('game.deck_limits'));

    $devTools = new DevTools(
        $users, $collection, new BoosterModel($pdo), $scores, new AchievementModel($pdo), $engine, $game, $abilities, $cards,
        array_keys($difficulties), array_keys(Config::get('achievements.list')), Config::get('game.starter_cards'),
        Config::get('app.dev.test_prefix'), Config::get('app.dev.test_password'), Config::get('game.welcome_boosters'),
    );

    return [
        'auth'        => $auth,
        'authPages'   => new AuthController($view, $auth, $game),
        'game'        => new GameController($view, $game, $auth),
        'boosters'    => new BoosterController($view, $auth, $boosters, $collection, $achievements),
        'collection'  => new CollectionController($view, $auth, $cardModel, $collection, $scores),
        'leaderboard' => new LeaderboardController($view, $scores, $achievements),
        'dev'         => new DevController($view, $auth, $devAccess, $devTools, $cards),
        'profile'     => new ProfileController($view, $auth, $scores, $achievements, $collection, count($cards)),
    ];
};
