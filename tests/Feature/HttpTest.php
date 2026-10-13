<?php

declare(strict_types=1);

// Routes, contrôleurs et câblage, avec un faux Leaf/Blade et SQLite. Lancer : composer test

require_once dirname(__DIR__) . '/leaf_stubs.php';

$_SESSION = [];

use App\Core\Csrf;

$pdo = testDatabase();
$services = (require BASE_PATH . '/app/bootstrap.php')($pdo);
(require BASE_PATH . '/app/routes.php')($services);

function go(string $method, string $path, array $post = [], bool $token = true): array
{
    $_POST = $post;
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    if ($token) {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
    }

    return app()->dispatch($method, $path);
}

$r = go('GET', '/');
check('/ sans compte → /login', $r['type'] === 'redirect' && $r['body'] === '/login');
$r = go('GET', '/game');
check('/game sans compte → /login', $r['type'] === 'redirect' && $r['body'] === '/login');
$r = go('GET', '/api/game/state');
check('API sans compte → 401', $r['status'] === 401 && isset($r['body']['error']));
$r = go('GET', '/login');
check('/login affiche la vue', $r['type'] === 'html' && str_contains($r['body'], 'auth.login'));
$r = go('POST', '/register', ['username' => 'alice', 'password' => 'secret123'], false);
check('POST sans jeton CSRF → 419', $r['status'] === 419);
$r = go('POST', '/register', ['username' => 'a', 'password' => 'x']);
check('inscription invalide → 422 + vue', $r['status'] === 422 && str_contains($r['body'], 'auth.register'));
$r = go('POST', '/register', ['username' => 'alice', 'password' => 'secret123']);
check('inscription valide → /game', $r['type'] === 'redirect' && $r['body'] === '/game');
$r = go('GET', '/login');
check('connecté : /login → /game', $r['type'] === 'redirect' && $r['body'] === '/game');
$r = go('GET', '/game');
check('/game rend game.play avec difficulties et deckSize',
    str_contains($r['body'], 'game.play') && str_contains($r['body'], 'difficulties') && str_contains($r['body'], 'deckSize'));
$r = go('GET', '/api/game/state');
check('état sans partie → game null', $r['status'] === 200 && $r['body']['game'] === null);

$deck = array_slice((new App\Models\CollectionModel($pdo))->cardIds($services['auth']->userId()), 0, 10);
$r = go('POST', '/api/game/start', ['difficulty' => 'inconnue', 'deck' => $deck]);
check('difficulté invalide → 422', $r['status'] === 422 && isset($r['body']['error']));
$r = go('POST', '/api/game/start', ['difficulty' => 'normal', 'deck' => $deck], false);
check('start sans jeton → 419', $r['status'] === 419);
$r = go('POST', '/api/game/start', ['difficulty' => 'normal', 'deck' => [1, 2]]);
check('deck incomplet → 422', $r['status'] === 422 && isset($r['body']['error']));
$r = go('POST', '/api/game/start', ['difficulty' => 'normal', 'deck' => $deck]);
$g = $r['body']['game'] ?? [];
check('start → partie en cours avec carte HTML', $r['status'] === 200 && $g['status'] === 'playing' && count($g['hand_html']) === 2 && str_contains($g['hand_html'][0], 'partials.card') && $g['announced'] === null);
check('start ne fuite pas les cartes brutes', !isset($g['player_hand']) && !isset($g['ai_card']) && !isset($g['reward']));

check('la vue contient la pile adverse et les capacités', isset($g['pile_html'], $g['abilities']['scan']) && $g['boost_bonus'] === 15);
$r = go('POST', '/api/game/ability', ['ability' => 'scan', 'attribute' => 'speed']);
check('scan → valeur révélée sur la carte du joueur', $r['status'] === 200 && $r['body']['game']['scan'] !== [] && $r['body']['game']['abilities']['scan'] === 0 && str_contains($r['body']['game']['hand_html'][0], 'foe'));
$r = go('POST', '/api/game/ability', ['ability' => 'scan', 'attribute' => 'speed']);
check('scan déjà utilisé → 422', $r['status'] === 422);
$r = go('POST', '/api/game/ability', ['ability' => 'boost'], false);
check('capacité sans jeton → 419', $r['status'] === 419);
$r = go('POST', '/api/game/ability', ['ability' => 'boost']);
check('boost armé', $r['status'] === 200 && $r['body']['game']['boost'] === true);

$r = go('POST', '/api/game/play', ['hand' => 0, 'attribute' => 'nimporte']);
check('attribut invalide → 422', $r['status'] === 422);

$leader = $g['leader'];
$r = go('POST', '/api/game/play', ['hand' => 1, 'attribute' => 'hacking']);
$res = $r['body']['result'] ?? null;
check('play → résultat complet', $r['status'] === 200 && $res !== null
    && $res['attribute_label'] === 'Piratage' && in_array($res['outcome'], ['player', 'ai', 'tie'], true)
    && str_contains($res['ai_html'], 'partials.card') && isset($r['body']['game']));

// Joue jusqu'à la fin pour couvrir le score, la récompense et l'écran final.
$guard = 0;
while (($r['body']['game']['status'] ?? '') === 'playing' && $guard++ < 100) {
    $r = go('POST', '/api/game/play', ['hand' => 0, 'attribute' => 'power']);
}
$final = $r['body']['game'] ?? [];
check('partie terminée', in_array($final['status'] ?? '', ['win', 'lose', 'draw'], true));

$r = go('POST', '/api/game/abandon');
check('abandonner une partie terminée → 422', $r['status'] === 422);
go('POST', '/api/game/start', ['difficulty' => 'normal', 'deck' => $deck]);
$before = (int) $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn();
$r = go('POST', '/api/game/abandon', [], false);
check('abandon sans jeton → 419', $r['status'] === 419);
$r = go('POST', '/api/game/abandon');
check('abandon → plus de partie', $r['status'] === 200 && $r['body']['game'] === null && go('GET', '/api/game/state')['body']['game'] === null);
check('abandon enregistré comme une défaite', (int) $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn() === $before + 1
    && $pdo->query('SELECT result FROM scores ORDER BY id DESC LIMIT 1')->fetchColumn() === 'lose');

$r = go('GET', '/collection');
check('/collection rendue', $r['type'] === 'html' && str_contains($r['body'], 'collection.index'));
$r = go('GET', '/leaderboard');
check('/leaderboard rendue', $r['type'] === 'html' && str_contains($r['body'], 'leaderboard.index'));

$r = go('GET', '/profile');
check('/profile rendue avec les succès', $r['type'] === 'html' && str_contains($r['body'], 'profile.index') && str_contains($r['body'], 'overview') && str_contains($r['body'], 'stats'));
$r = go('POST', '/profile/title', ['title' => 'ghost']);
check('choisir un titre : retour au profil', $r['type'] === 'redirect' && $r['body'] === '/profile');
check('titre non débloqué : pas enregistré', $pdo->query("SELECT title FROM users WHERE username = 'alice'")->fetchColumn() === null);
$r = go('POST', '/profile/title', ['title' => 'recruit'], false);
check('titre sans jeton → 419', $r['status'] === 419);

go('POST', '/api/game/start', ['difficulty' => 'normal', 'deck' => $deck]);
$before = (int) $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn();
$r = go('POST', '/logout');
check('se déconnecter en pleine partie compte une défaite', (int) $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn() === $before + 1
    && $pdo->query('SELECT result FROM scores ORDER BY id DESC LIMIT 1')->fetchColumn() === 'lose');
check('logout → /login', $r['type'] === 'redirect' && $r['body'] === '/login');
$r = go('GET', '/profile');
check('après logout, /profile → /login', $r['body'] === '/login');
$r = go('POST', '/login', ['username' => 'alice', 'password' => 'faux']);
check('mauvais mot de passe → 422', $r['status'] === 422);
$r = go('POST', '/login', ['username' => 'alice', 'password' => 'secret123']);
check('bon mot de passe → /game', $r['type'] === 'redirect' && $r['body'] === '/game');

restore_error_handler();
