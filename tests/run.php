<?php

declare(strict_types=1);

// Lancer : php tests/run.php

require __DIR__ . '/bootstrap.php';

use App\Core\Config;
use App\Core\Session;
use App\Models\AchievementModel;
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
use App\Services\Game\GameEngine;
use App\Models\BoosterModel;
use App\Services\Boosters\Boosters;
use App\Services\Game\GameService;
use App\Services\Boosters\Rewards;

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    $condition ? $passed++ : $failed++;
    echo ($condition ? '  ok   ' : '  FAIL ') . $name . "\n";
}

/** Même nom que dans Pest (tests/Pest.php) : les fichiers de tests/Feature servent aux deux lanceurs. */
function raises(callable $action, string $class): bool
{
    return throws($action, $class);
}

function throws(callable $action, string $class): bool
{
    try {
        $action();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }

    return false;
}

$attributes = Config::get('game.attributes');
$keys = array_keys($attributes);

// ---------------------------------------------------------------- Données
echo "Données\n";
$pdo = testDatabase();
$cardModel = new CardModel($pdo, $keys);
$cards = $cardModel->all();

check('60 cartes (minimum du sujet : 20)', count($cards) === 60);
$columns = array_column($pdo->query('PRAGMA table_info(cards)')->fetchAll(), 'name');
check('chaque attribut de config/game.php est une colonne de cards', array_diff($keys, $columns) === []);
check('au moins 4 attributs numériques par carte', count($keys) >= 4);
check('valeurs entre 1 et 100', array_reduce($cards, fn ($ok, $c) => $ok && min(array_intersect_key($c, array_flip($keys))) >= 1 && max(array_intersect_key($c, array_flip($keys))) <= 100, true));
check('chaque rareté des cartes existe dans la config', array_diff(array_column($cards, 'rarity'), array_keys(Config::get('game.rarities'))) === []);
check('31 communes, 16 rares, 7 épiques, 6 légendaires', array_count_values(array_column($cards, 'rarity')) === ['common' => 31, 'rare' => 16, 'epic' => 7, 'legendary' => 6]);
check('cartes de départ >= cartes par partie', Config::get('game.starter_cards') >= Config::get('game.deck_size'));
check('assez de communes pour le départ', count($cardModel->idsByRarity('common')) >= Config::get('game.starter_cards'));

// ---------------------------------------------------------------- Moteur
echo "Moteur de règles\n";
$fake = [
    1 => ['id' => 1, 'rarity' => 'common', 'power' => 90, 'speed' => 10],
    2 => ['id' => 2, 'rarity' => 'common', 'power' => 50, 'speed' => 80],
    3 => ['id' => 3, 'rarity' => 'common', 'power' => 50, 'speed' => 20],
    4 => ['id' => 4, 'rarity' => 'common', 'power' => 70, 'speed' => 70],
];
$fakeKeys = ['power', 'speed'];
$fakeEngine = new GameEngine($fake, $fakeKeys, new AiPlayer($fake, $fakeKeys), 20, 2);

$state = $fakeEngine->start([1, 3], [2, 4], 'normal');
[$state, $r] = $fakeEngine->play($state, 0, 'power');        // 90 contre la meilleure réponse (carte 4 : 70) : le joueur gagne
check('la valeur la plus haute gagne ; l\'ordinateur répond avec sa meilleure carte', $r['outcome'] === 'player' && $r['ai_card']['id'] === 4);
check('le gagnant range les deux cartes sous sa pile', $state['player'] === [3, 1, 4] && $state['ai'] === [2]);
check('le meneur alterne : manche 2 = ordinateur, qui a déjà annoncé', $state['leader'] === 'ai' && $state['announced'] === ['index' => 0, 'attribute' => 'speed']);

[$state, $r] = $fakeEngine->play($state, 0, null);           // carte 3 (vitesse 20) contre la vitesse 80 annoncée : perdu
check('le joueur répond à l\'attribut annoncé', $r['outcome'] === 'ai' && $r['chosen_by'] === 'ai' && $r['attribute'] === 'speed');
check('manche 3 = joueur, plus d\'annonce', $state['leader'] === 'player' && $state['announced'] === null);

$state = $fakeEngine->start([1, 2, 3], [4, 4, 4], 'normal');
[$state] = $fakeEngine->play($state, 1, 'power');            // on joue la 2e carte de la main : la 1re reste en main
check('choisir la 2e carte de la main laisse la 1re', $state['player'] === [1, 3]);
check('carte hors de la main refusée', throws(fn () => $fakeEngine->play($fakeEngine->start([1, 2, 3], [4, 4, 4], 'normal'), 2, 'power'), InvalidArgumentException::class));

$state = $fakeEngine->start([2, 1], [3, 3], 'normal');
[$state, $r] = $fakeEngine->play($state, 0, 'power');        // 50 contre 50 : égalité
check('égalité : cartes dans la cagnotte, personne ne gagne', $r['outcome'] === 'tie' && $state['pot'] === [2, 3]);
$state['leader'] = 'player';
$state['announced'] = null;
[$state, $r] = $fakeEngine->play($state, 0, 'power');        // 90 contre 50 : le joueur prend la cagnotte
check('le prochain vainqueur gagne la cagnotte', $state['pot'] === [] && count($state['player']) === 4 && $state['status'] === 'win');

$state = $fakeEngine->start([1], [2], 'normal');
check('attribut inconnu refusé', throws(fn () => $fakeEngine->play($state, 0, 'chance'), InvalidArgumentException::class));
[$done] = $fakeEngine->play($state, 0, 'power');
check('partie terminée : plus de manche possible', throws(fn () => $fakeEngine->play($done, 0, 'power'), DomainException::class));

$state = $fakeEngine->start([3], [4], 'normal');
[$state] = $fakeEngine->play($state, 0, 'power');
check('plus de cartes = défaite', $state['status'] === 'lose');

$short = new GameEngine($fake, $fakeKeys, new AiPlayer($fake, $fakeKeys), 2, 2);
$state = $short->start([1, 3, 3], [2, 4, 4], 'normal');
[$state] = $short->play($state, 0, 'power');
[$state] = $short->play($state, 0, null);
check('limite de manches : le plus de cartes gagne', $state['status'] !== 'playing' && $state['round'] === 2);

$state = $fakeEngine->start([1, 1], [4, 4], 'normal');
$state['leader'] = 'ai';
$state['announced'] = ['index' => 0, 'attribute' => 'speed'];
[$state, $r] = $fakeEngine->play($state, 0, 'power');
check("quand l'ordinateur mène, l'attribut demandé est ignoré", $r['chosen_by'] === 'ai' && $r['attribute'] === 'speed');

// Parties complètes sur les vraies cartes : aucune carte perdue ni créée, et ça se termine toujours.
mt_srand(2026);
$engine = new GameEngine($cards, $keys, new AiPlayer($cards, $keys), Config::get('game.max_rounds'), Config::get('game.hand_size'));
$ids = array_keys($cards);
$valid = true;
$statuses = [];
foreach (['easy', 'normal', 'hard'] as $difficulty) {
    for ($g = 0; $g < 100; $g++) {
        shuffle($ids);
        $state = $engine->start(array_slice($ids, 0, 10), array_slice($ids, 10, 10), $difficulty);
        while ($state['status'] === 'playing') {
            [$state] = $engine->play($state, mt_rand(0, min(1, count($state['player']) - 1)), $keys[array_rand($keys)]);
            $valid = $valid && count($state['player']) + count($state['ai']) + count($state['pot']) === 20;
        }
        $valid = $valid && $state['round'] <= Config::get('game.max_rounds');
        $statuses[$state['status']] = true;
    }
}
check('300 parties complètes : 20 cartes en jeu à chaque manche, fin avant la limite', $valid);
check('toutes les issues se produisent (win, lose)', isset($statuses['win'], $statuses['lose']));

// ---------------------------------------------------------------- IA
echo "IA\n";
$ai = new AiPlayer($cards, $keys);
$netrunner = $cards[array_search('netrunner', array_column($cards, 'slug', 'id'), true)];
check('easy : renvoie toujours une carte et un attribut valides', array_reduce(range(1, 50), function ($ok) use ($ai, $netrunner, $keys) {
    [$index, $attribute] = $ai->lead([$netrunner, $netrunner], 'easy');
    return $ok && in_array($index, [0, 1], true) && in_array($attribute, $keys, true);
}, true));
check('normal : annonce la plus haute valeur de sa main', $ai->lead([$netrunner], 'normal') === [0, 'hacking']);
$weak = ['id' => 0, 'power' => 5, 'speed' => 5, 'intelligence' => 5, 'hacking' => 5, 'threat' => 5];
check('normal : choisit la carte qui porte la plus haute valeur', $ai->lead([$weak, $netrunner], 'normal')[0] === 1);
check('normal : répond avec la carte la plus forte sur l\'attribut annoncé', $ai->answer([$weak, $netrunner], 'hacking', 'normal') === 1);
check('easy : répond avec une carte de sa main', in_array($ai->answer([$weak, $netrunner], 'hacking', 'easy'), [0, 1], true));

$pool = [
    ['id' => 1, 'a' => 10, 'b' => 50], ['id' => 2, 'a' => 20, 'b' => 60],
    ['id' => 3, 'a' => 30, 'b' => 70], ['id' => 4, 'a' => 95, 'b' => 80],
];
$smart = new AiPlayer($pool, ['a', 'b']);
$card = ['a' => 90, 'b' => 85];
check('hard : privilégie l\'attribut où la carte domine le jeu (pas la plus haute valeur brute)',
    $smart->lead([$card], 'normal')[1] === 'a' && $smart->lead([$card], 'hard')[1] === 'b');

// ---------------------------------------------------------------- Récompenses
echo "Récompenses\n";
$weights = array_map(fn ($r) => $r['weight'], Config::get('game.rarities'));
$rewards = new Rewards($weights);
$owned = array_slice(array_keys($cards), 0, 12);
$never = true;
for ($i = 0; $i < 500; $i++) {
    $never = $never && !in_array($rewards->pick($cards, $owned), $owned, true);
}
check('ne donne jamais une carte déjà possédée', $never);
check('renvoie null si tout est déjà possédé', $rewards->pick($cards, array_keys($cards)) === null);
$first = array_keys(array_diff_key($cards, array_flip($owned)))[0];
check('tirage minimal = première carte disponible', (new Rewards($weights, fn () => 1))->pick($cards, $owned) === $first);
$tally = array_fill_keys(array_keys($cards), 0);
$rolls = 0;
$cursor = new Rewards($weights, function (int $max) use (&$rolls) { return ($rolls++ % $max) + 1; });
for ($i = 0; $i < 2000; $i++) {
    $tally[$cursor->pick($cards, [])]++;
}
check('une commune sort plus souvent qu\'une légendaire', $tally[array_key_first($cards)] > $tally[array_key_last($cards)]);

// ---------------------------------------------------------------- Comptes
echo "Comptes\n";
$users = new UserModel($pdo);
$collection = new CollectionModel($pdo);
$auth = new AuthService($users, $collection, $cardModel, Config::get('game.starter_cards'), new BoosterModel($pdo), Config::get('game.welcome_boosters'));

check('pseudo trop court refusé', isset($auth->register('ab', 'motdepasse1')['username']));
check('caractères interdits refusés', isset($auth->register('a b!', 'motdepasse1')['username']));
check('mot de passe trop court refusé', isset($auth->register('alice', 'court')['password']));
check('mot de passe trop long refusé (limite bcrypt)', isset($auth->register('alice', str_repeat('x', 73))['password']));
check('rien n\'est créé en cas d\'erreur', $users->findByUsername('alice') === null);

check('inscription valide', $auth->register('alice', 'motdepasse1') === []);
$aliceId = $auth->userId();
check('inscrit = connecté (session)', $aliceId !== null && $auth->username() === 'alice');
check('mot de passe haché en base', password_verify('motdepasse1', $users->findByUsername('alice')['password_hash']));
$starter = $collection->cardIds($aliceId);
check('12 cartes de départ, toutes communes', count($starter) === 12 && count(array_filter($starter, fn ($id) => $cards[$id]['rarity'] !== 'common')) === 0);
$welcome = array_count_values(array_column((new BoosterModel($pdo))->stock($aliceId), 'difficulty'));
check('boosters de bienvenue : 2 par difficulté', $welcome === Config::get('game.welcome_boosters'));
check('pseudo déjà pris refusé', isset($auth->register('alice', 'autrechose1')['username']));

$auth->logout();
check('déconnexion', $auth->userId() === null && $_SESSION === []);
check('mauvais mot de passe refusé', $auth->login('alice', 'faux') === false && $auth->userId() === null);
check('pseudo inconnu refusé', $auth->login('inconnu', 'motdepasse1') === false);
check('connexion valide', $auth->login('alice', 'motdepasse1') && $auth->userId() === $aliceId);

// ---------------------------------------------------------------- Paquet de l'ordinateur
echo "Paquet de l'ordinateur\n";
$aiDeck = new AiDeck($cards);
$rarityOf = function (array $ids) use ($cards): array {
    $counts = array_count_values(array_map(fn ($id) => $cards[$id]['rarity'], $ids));
    ksort($counts);

    return $counts;
};
$byRarity = fn (string $r): array => array_keys(array_filter($cards, fn ($c) => $c['rarity'] === $r));
$mixedHand = [...array_slice($byRarity('common'), 0, 3), ...array_slice($byRarity('rare'), 0, 2), ...array_slice($byRarity('epic'), 0, 1), ...array_slice($byRarity('legendary'), 0, 1)];   // une de chaque rareté, plusieurs communes
$fairDeck = true;
for ($i = 0; $i < 100; $i++) {
    $deck = $aiDeck->matching($mixedHand);
    $sameRarities = $rarityOf($deck) === $rarityOf($mixedHand);
    $fairDeck = $fairDeck && $sameRarities && count($deck) === count($mixedHand) && array_intersect($deck, $mixedHand) === [];
}
check("l'ordinateur reçoit les mêmes raretés que le joueur, sans doublon avec sa main", $fairDeck);
check('pas de légendaire pour l\'ordinateur si le joueur n\'en a pas', !in_array('legendary', array_map(fn ($id) => $cards[$id]['rarity'], $aiDeck->matching([1, 2, 3, 4])), true));
$allLegendaries = array_keys(array_filter($cards, fn ($c) => $c['rarity'] === 'legendary'));
check('rareté épuisée : exemplaire de la carte du joueur', count($aiDeck->matching($allLegendaries)) === count($allLegendaries));

// ---------------------------------------------------------------- Partie complète via le service
echo "Service de jeu\n";
$scores = new ScoreModel($pdo);
$achievementRules = Config::get('achievements.list');
$achievementMetrics = new AchievementMetrics($cards, 'hard', 'common', 'legendary');
$achievements = new AchievementService(new AchievementModel($pdo), $scores, $collection, $users, $achievementMetrics, $achievementRules, Config::get('achievements.titles'), Config::get('achievements.categories'));
$noShiny = $achievementMetrics->compute([], [1], null);
$allShiny = $achievementMetrics->compute([], [1], null, array_keys($cards));
check('mesure : skins shiny manquants (aucun, puis tous débloqués)', $noShiny['shiny_missing'] === count($cards) && $allShiny['shiny_missing'] === 0);
check('succès « Prismatique » : atteint seulement avec tous les skins', in_array('all_shiny', $achievements->reached($allShiny), true) && !in_array('all_shiny', $achievements->reached($noShiny), true));
check('titre Shiny : disponible avec le succès, absent sans', $achievements->title('shiny', ['all_shiny'], null)['code'] === 'shiny' && $achievements->title('shiny', [], null)['code'] !== 'shiny');
$boosterStock = new BoosterModel($pdo);
$boosters = new Boosters($boosterStock, $collection, $users, new Rewards($weights), $cards, Config::get('game.boosters'), Config::get('game.shiny'));
$engine = new GameEngine($cards, $keys, new AiPlayer($cards, $keys), Config::get('game.max_rounds'), Config::get('game.hand_size'), 15);
$abilities = new Abilities(['scan' => 1, 'swap' => 1, 'boost' => 1], $cards, $keys, 2);
$game = new GameService($engine, $collection, $scores, $boosters, new AiDeck($cards), $abilities, $cards, 10, ['easy', 'normal', 'hard'], $achievements, Config::get('game.deck_limits'));
$deckOf = fn () => array_slice($collection->cardIds($aliceId), 0, 10);

check('difficulté inconnue refusée', throws(fn () => $game->start($aliceId, 'impossible', $deckOf()), InvalidArgumentException::class));
check('pas de partie au départ', $game->current($aliceId) === null);
check('jouer sans partie refusé', throws(fn () => $game->play($aliceId, 0, 'power'), DomainException::class));

$notOwned = array_values(array_diff(array_keys($cards), $collection->cardIds($aliceId)))[0];
check('deck trop petit refusé', throws(fn () => $game->start($aliceId, 'easy', [1, 2, 3]), DomainException::class));
check('deck avec doublon refusé', throws(fn () => $game->start($aliceId, 'easy', [1, 1, 2, 3, 4, 5, 6, 7, 8, 9]), DomainException::class));
// Limite de rareté : un second service qui n'autorise que 3 cartes communes (les cartes de départ sont toutes communes).
$limited = new GameService($engine, $collection, $scores, $boosters, new AiDeck($cards), $abilities, $cards, 10, ['easy', 'normal', 'hard'], $achievements, ['common' => 3]);
check('deck au-delà de la limite de rareté refusé', throws(fn () => $limited->start($aliceId, 'easy', $deckOf()), DomainException::class));
check('limites de rareté de la config : légendaire 3, épique 4', Config::get('game.deck_limits') === ['legendary' => 3, 'epic' => 4]);
check('deck avec une carte non possédée refusé', throws(fn () => $game->start($aliceId, 'easy', [...array_slice($deckOf(), 0, 9), $notOwned]), DomainException::class));

$view = $game->start($aliceId, 'easy', $deckOf());
check('bilan absent pendant la partie', $view['summary'] === null);
check('partie démarrée : 10 cartes chacun', $view['player_count'] === 10 && $view['ai_count'] === 10 && $view['status'] === 'playing');
check('la main de l\'ordinateur n\'est pas exposée', !array_key_exists('ai_card', $view) && !array_key_exists('ai_cards', $view) && !str_contains(json_encode($view), 'ai_card'));
check('une autre personne ne voit pas cette partie', $game->current($aliceId + 1) === null);

$game->forfeit($aliceId); // la partie laissée en cours ci-dessus compte comme une défaite (voir « Quitter une partie »)
$scoresBefore = (int) $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn();
$ownedBefore = count($collection->cardIds($aliceId));
$rounds = 0;
$wins = 0;
$games = 0;
while ($games < 40) {
    $view = $game->start($aliceId, 'easy', array_slice($collection->cardIds($aliceId), 0, 10));
    while ($view['status'] === 'playing') {
        $card = $view['player_hand'][0];
        $best = array_keys($card, max(array_intersect_key($card, array_flip($keys))), true)[0];
        $view = $game->play($aliceId, 0, $best)['game'];
        $rounds++;
    }
    $games++;
    $wins += (int) ($view['status'] === 'win');
}
$summary = $view['summary'];
check('bilan de fin de partie : manches gagnées + perdues + égalités = manches jouées',
    $summary['won'] + $summary['lost'] + $summary['tied'] === $view['round'] && count($summary['rounds']) === $view['round']);
$recorded = (int) $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn();
check("40 parties jouées jusqu'au bout, 40 scores enregistrés", $recorded === $scoresBefore + 40);
check('au moins une victoire contre l\'IA easy', $wins > 0);
$welcomeCount = array_sum(Config::get('game.welcome_boosters')); // les boosters offerts à l'inscription sont déjà en stock
check('chaque victoire ajoute un booster au stock (pas directement une carte)',
    count($boosterStock->stock($aliceId)) === $welcomeCount + $wins && count($collection->cardIds($aliceId)) === $ownedBefore);
check('rejouer une partie terminée est refusé', throws(fn () => $game->play($aliceId, 0, 'power'), DomainException::class));

$tiny = new GameService($engine, $collection, $scores, $boosters, new AiDeck($cards), $abilities, $cards, 30, ['easy'], $achievements);
check('deck de la mauvaise taille : refusé', throws(fn () => $tiny->start($aliceId, 'easy', $deckOf()), DomainException::class));

// ---------------------------------------------------------------- Capacités, mémoire, boost
echo "Capacités\n";
$abilityEngine = new GameEngine($fake, $fakeKeys, new AiPlayer($fake, $fakeKeys), 20, 2, 15);
$abilityRules = new Abilities(['scan' => 1, 'swap' => 1, 'boost' => 1], $fake, $fakeKeys, 2);
$fresh = fn () => $abilityEngine->start([3, 1], [4, 2], 'normal', $abilityRules->initial());

$scanned = $abilityRules->use($fresh(), 'scan', 'speed');
check('scan : révèle la meilleure vitesse de la main adverse (cartes 4 et 2 : 80)', $scanned['scan'] === ['speed' => 80] && $scanned['abilities']['scan'] === 0);
check('scan : une seule utilisation', throws(fn () => $abilityRules->use($scanned, 'scan', 'power'), DomainException::class));
check('scan : attribut inconnu refusé', throws(fn () => $abilityRules->use($fresh(), 'scan', 'chance'), InvalidArgumentException::class));
check('capacité inconnue refusée', throws(fn () => $abilityRules->use($fresh(), 'laser', null), InvalidArgumentException::class));

$swapped = $abilityRules->use($abilityEngine->start([3, 1, 2], [4, 2, 1], 'normal', $abilityRules->initial()), 'swap', null);
check('swap : la première carte de la main repart en bas', $swapped['player'] === [1, 2, 3]);
check('swap : refusé sans carte de plus dans la pile', throws(fn () => $abilityRules->use($fresh(), 'swap', null), DomainException::class));

$boosted = $abilityRules->use($fresh(), 'boost', null);
[$afterBoost, $r] = $abilityEngine->play($boosted, 0, 'power');   // carte 3 : 50 + 15 = 65 contre 70 (carte 4) : perdu quand même
check('boost : +15 compté et signalé', $r['player_value'] === 65 && $r['boosted'] === true && $r['outcome'] === 'ai');
[$boostedWin] = $abilityEngine->play($abilityRules->use($abilityEngine->start([3, 1], [3, 2], 'normal', ['boost' => 1]), 'boost', null), 0, 'power');
check('boost : fait gagner une égalité (50+15 contre 50)', $boostedWin['player'] === [1, 3, 3]);
check('boost et scan consommés par la manche', $afterBoost['boost'] === false && $afterBoost['scan'] === []);
check('boost : une seule fois armé', throws(fn () => $abilityRules->use($boosted, 'boost', null), DomainException::class));

$aiLeads = $afterBoost;   // manche 2 : l'ordinateur mène
$defending = $abilityEngine->start([3, 1, 2], [4, 2, 1], 'normal', $abilityRules->initial());
$defending['leader'] = 'ai';
$defending['announced'] = ['index' => 0, 'attribute' => 'speed'];
$scanDefense = $abilityRules->use($defending, 'scan', null);
check("scan en défense : donne la valeur exacte de la carte jouée par l'ordinateur", $scanDefense['scan'] === ['speed' => $fake[4]['speed']] && $scanDefense['abilities']['scan'] === 0);
$swapDefense = $abilityRules->use($defending, 'swap', null);
check('swap en défense : la première carte de la main repart en bas', $swapDefense['player'] === [1, 2, 3] && $swapDefense['announced'] === $defending['announced']);
check("boost refusé quand l'ordinateur mène", throws(fn () => $abilityRules->use($defending, 'boost', null), DomainException::class));

echo "Mémoire\n";
$memory = $abilityEngine->start([1, 3], [2, 4], 'normal');
check('au départ, aucune carte n\'est connue', $memory['known'] === []);
[$memory, $played] = $abilityEngine->play($memory, 0, 'power');
$memoryWon = $played['outcome'] === 'ai';
check('l\'ordinateur ne ramasse que ce qu\'il a vu : connu seulement s\'il gagne la manche', $memory['known'] === ($memoryWon ? array_values($memory['known']) : []) && count($memory['known']) === ($memoryWon ? 2 : 0));
$twins = $abilityEngine->start([1, 3], [1, 4], 'normal');
$twins['known'] = [1];
[$twins] = $abilityEngine->play($twins, 0, 'power');
check('carte en double : jouer l\'exemplaire adverse n\'efface qu\'un exemplaire connu', count(array_keys($twins['known'], 1, true)) <= 1);

mt_srand(7);
$started = $game->start($aliceId, 'easy', $deckOf());
$state = $_SESSION['game'];
$shownIds = array_column($started['ai_known'], 'id');
check('pile adverse : seules les cartes déjà vues sont exposées', array_diff($shownIds, $state['known']) === []);
check('pile adverse : le compte inconnu est exact', $started['ai_unknown'] === 10 - count($shownIds));
check('pile adverse : triée par id, donc sans indice sur l\'ordre', $shownIds === (function ($ids) { sort($ids); return $ids; })($shownIds));
check('capacités et boost visibles dans la vue', $started['abilities'] === ['scan' => 1, 'swap' => 1, 'boost' => 1] && $started['boost'] === false);

// ---------------------------------------------------------------- Classement
echo "Classement\n";
$pdo->exec('DELETE FROM scores');
$bobId = $users->create('bob', 'x');
foreach ([[$aliceId, 'easy', 'win'], [$aliceId, 'easy', 'win'], [$aliceId, 'normal', 'lose'],
          [$bobId, 'hard', 'win'], [$bobId, 'normal', 'lose']] as [$u, $d, $res]) {
    $scores->record($u, $d, $res, 12);
}
$board = $scores->leaderboard(10);
check('alice (2 victoires) devant bob (1)', $board[0]['username'] === 'alice' && $board[0]['wins'] === 2 && $board[1]['wins'] === 1);
check('détail par difficulté', $board[0]['by_difficulty'] === ['easy' => ['games' => 2, 'wins' => 2], 'normal' => ['games' => 1, 'wins' => 0]] && $board[0]['rate'] === 67);
check('victoires et parties comptées', $board[0]['wins'] === 2 && $board[0]['games'] === 3 && $board[1]['games'] === 2);
check('limite respectée', count($scores->leaderboard(1)) === 1);
check('joueur sans partie : zéro', $scores->statsFor('personne') === ['games' => 0, 'wins' => 0, 'rate' => 0, 'by_difficulty' => []]);

require __DIR__ . '/Feature/BoosterTest.php';
require __DIR__ . '/Feature/DevTest.php';

echo "\n{$passed} réussis, {$failed} échoués\n";
exit($failed === 0 ? 0 : 1);
