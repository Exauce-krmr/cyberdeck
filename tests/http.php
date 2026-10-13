<?php

declare(strict_types=1);

// Lancer : php tests/http.php — routes, contrôleurs et câblage, avec un faux Leaf/Blade et SQLite.

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/leaf_stubs.php';

use App\Core\Csrf;

$passed = $failed = 0;
function check(string $name, bool $ok): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  ok   ' : '  FAIL ') . $name . "\n";
}

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
$r = go('GET', '/dev');
check('/dev sans compte → /login', $r['type'] === 'redirect' && $r['body'] === '/login');
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

// Le Labo : fermé par défaut (404 comme si la page n'existait pas). Pour tester le côté ouvert : DEV_TOOLS=1 DEV_USERS=alice php tests/http.php
$devOpen = in_array('alice', (array) App\Core\Config::get('app.dev.users'), true) && App\Core\Config::get('app.dev.enabled') === true;
$r = go('GET', '/dev');
check($devOpen ? '/dev : autorisé → vue dev.index' : '/dev : DEV_TOOLS éteint ou pseudo absent → 404', $devOpen ? str_contains($r['body'], 'dev.index') : $r['status'] === 404);
$r = go('POST', '/dev', ['action' => 'give_boosters', 'kind' => 'standard', 'difficulty' => 'easy', 'count' => '2']);
check($devOpen ? '/dev : action appliquée puis retour sur /dev' : '/dev : action refusée (404), rien n\'est modifié', $devOpen ? $r['type'] === 'redirect' && $r['body'] === '/dev' : $r['status'] === 404);
$welcome = array_sum(App\Core\Config::get('game.welcome_boosters')); // offerts à l'inscription
$given = count((new App\Models\BoosterModel($pdo))->stock($services['auth']->userId())) - $welcome;
check($devOpen ? '/dev : 2 boosters ajoutés' : '/dev : aucun booster ajouté sans autorisation', $given === ($devOpen ? 2 : 0));
$r = go('POST', '/dev', ['action' => 'give_boosters', 'kind' => 'standard', 'difficulty' => 'easy', 'count' => '2'], false);
check('/dev sans jeton CSRF → 419', $r['status'] === 419);

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
echo '  (statut final : ' . ($final['status'] ?? '?') . ")\n";

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

// ---- Boosters : un booster est donné par victoire, l'ouverture passe par l'API
$uid = $services['auth']->userId();
$stockModel = new App\Models\BoosterModel($pdo);
check('victoire ou non, le stock est cohérent (0 ou 1 booster après une partie)', count($stockModel->stock($uid)) === $welcome + $given + (($final['status'] ?? '') === 'win' ? 1 : 0)); // $given : boosters déjà donnés par le Labo ci-dessus (0 s'il est fermé)
$stockModel->add($uid, 'standard', 'normal');
$r = go('GET', '/boosters');
check('/boosters rendue avec le stock', $r['type'] === 'html' && str_contains($r['body'], 'boosters.index') && str_contains($r['body'], 'overview'));
$r = go('POST', '/api/boosters/open', ['id' => 999999]);
check('ouvrir un booster inexistant → 422', $r['status'] === 422 && isset($r['body']['error']));
$r = go('POST', '/api/boosters/open', ['id' => $stockModel->stock($uid)[0]['id']], false);
check('ouvrir sans jeton → 419', $r['status'] === 419);
$r = go('POST', '/api/boosters/open', ['id' => $stockModel->stock($uid)[0]['id']]);
check('ouvrir un booster → cartes en HTML, vue d\'ensemble à jour', $r['status'] === 200 && count($r['body']['opened']['cards_html']) >= 1 && str_contains($r['body']['opened']['cards_html'][0], 'partials.card') && isset($r['body']['overview']['stock']));
$r = go('POST', '/api/boosters/buy-shiny');
check('acheter un booster shiny sans collection complète → 422', $r['status'] === 422);
$r = go('POST', '/api/collection/shiny', ['card' => 1]);
check('skin shiny non débloqué → 422', $r['status'] === 422);

$r = go('GET', '/collection');
check('/collection rendue', $r['type'] === 'html' && str_contains($r['body'], 'collection.index'));
$r = go('GET', '/leaderboard');
check('/leaderboard rendue', $r['type'] === 'html' && str_contains($r['body'], 'leaderboard.index'));

$r = go('POST', '/logout');
check('logout → /login', $r['type'] === 'redirect' && $r['body'] === '/login');
$r = go('GET', '/collection');
check('après logout, /collection → /login', $r['body'] === '/login');
$r = go('POST', '/login', ['username' => 'alice', 'password' => 'faux']);
check('mauvais mot de passe → 422', $r['status'] === 422);
$r = go('POST', '/login', ['username' => 'alice', 'password' => 'secret123']);
check('bon mot de passe → /game', $r['type'] === 'redirect' && $r['body'] === '/game');

echo "\n$passed ok, $failed échec(s)\n";
exit($failed > 0 ? 1 : 0);
