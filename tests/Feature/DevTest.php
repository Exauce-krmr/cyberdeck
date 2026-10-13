<?php

declare(strict_types=1);

// Le Labo : verrous d'accès, boosters/collection, parties fabriquées (graine), comptes de test. SQLite en mémoire.

use App\Core\Config;
use App\Core\Session;
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
use App\Services\Boosters\Boosters;
use App\Services\Dev\DevAccess;
use App\Services\Dev\DevTools;
use App\Services\Game\GameEngine;
use App\Services\Game\GameService;
use App\Services\Boosters\Rewards;

(function (): void {
    $keys = array_keys(Config::get('game.attributes'));
    $pdo = testDatabase();
    $cards = (new CardModel($pdo, $keys))->all();
    $users = new UserModel($pdo);
    $collection = new CollectionModel($pdo);
    $scores = new ScoreModel($pdo);
    $stock = new BoosterModel($pdo);
    $achModel = new AchievementModel($pdo);
    $weights = array_map(fn (array $r): int => $r['weight'], Config::get('game.rarities'));
    $boosters = new Boosters($stock, $collection, $users, new Rewards($weights), $cards, Config::get('game.boosters'), Config::get('game.shiny'));
    $engine = new GameEngine($cards, $keys, new AiPlayer($cards, $keys), Config::get('game.max_rounds'), Config::get('game.hand_size'), 15);
    $abilities = new Abilities(['scan' => 1, 'swap' => 1, 'boost' => 1], $cards, $keys, 2);
    $achievements = new AchievementService($achModel, $scores, $collection, $users, new AchievementMetrics($cards, 'hard', 'common', 'legendary'), Config::get('achievements.list'), Config::get('achievements.titles'), Config::get('achievements.categories'));
    $game = new GameService($engine, $collection, $scores, $boosters, new AiDeck($cards), $abilities, $cards, 10, ['easy', 'normal', 'hard'], $achievements);
    $tools = new DevTools($users, $collection, $stock, $scores, $achModel, $engine, $game, $abilities, $cards, ['easy', 'normal', 'hard'], array_keys(Config::get('achievements.list')), 12, 'test_', 'pw-test-1234');

    // ---- Accès : deux verrous
    check('Labo : éteint (DEV_TOOLS=0), même un pseudo autorisé est refusé', (new DevAccess(false, ['exo']))->allows('exo') === false);
    check('Labo : allumé mais pseudo absent de la liste : refusé', (new DevAccess(true, ['exo']))->allows('alice') === false);
    check('Labo : allumé + pseudo autorisé (sans tenir compte de la casse) : accepté', (new DevAccess(true, ['Exo']))->allows('exo') === true);
    check('Labo : visiteur non connecté refusé', (new DevAccess(true, ['exo']))->allows(null) === false && (new DevAccess(true, [''])) ->allows('') === false);
    check('Labo : liste vide = personne', (new DevAccess(true, []))->allows('exo') === false);

    $me = $users->create('exo', 'x');

    // ---- Boosters et points
    $tools->giveBoosters($me, 'standard', 'hard', 3);
    $tools->giveBoosters($me, 'shiny', 'normal', 1);
    check('Labo : boosters donnés au stock', count($stock->stock($me)) === 4 && $tools->snapshot($me)['boosters'] === ['standard · hard' => 3, 'shiny · normal' => 1]);
    check('Labo : type ou difficulté inconnus refusés', raises(fn () => $tools->giveBoosters($me, 'or', 'hard', 1), InvalidArgumentException::class) && raises(fn () => $tools->giveBoosters($me, 'standard', 'zzz', 1), InvalidArgumentException::class));
    check('Labo : nombre de boosters borné', raises(fn () => $tools->giveBoosters($me, 'standard', 'easy', 500), InvalidArgumentException::class));
    $tools->clearBoosters($me);
    check('Labo : stock vidé', $stock->stock($me) === []);
    $tools->setShinyPoints($me, 7);
    check('Labo : points shiny fixés', $users->shinyPoints($me) === 7);

    // ---- Collection
    $tools->collection($me, 'all');
    check('Labo : collection complète', count($collection->cardIds($me)) === 60);
    $tools->collection($me, 'except', 2);
    check('Labo : tout sauf 2 cartes', count($collection->cardIds($me)) === 58);
    // ---- Cartes précises, compte visé
    $tools->collection($me, 'none');
    $tools->giveCards($me, [1, 2, 2]);
    check('Labo : cartes précises données (doublons ignorés)', $collection->cardIds($me) === [1, 2]);
    $tools->removeCards($me, [1]);
    check('Labo : carte précise retirée', $collection->cardIds($me) === [2]);
    check('Labo : donner sans carte, ou une carte inconnue : refusé', raises(fn () => $tools->giveCards($me, []), InvalidArgumentException::class) && raises(fn () => $tools->giveCards($me, [99999]), InvalidArgumentException::class));
    check('Labo : compte visé vide = le mien', $tools->targetId($me, '') === $me);
    check('Labo : un vrai compte ne peut pas être visé', raises(fn () => $tools->targetId($me, 'alice'), DomainException::class));
    $tools->createTestAccount('cible', 'rookie');
    $targetId = $tools->targetId($me, 'test_cible');
    $tools->giveCards($targetId, [1]);
    check('Labo : carte donnée à un compte de test, pas au mien', in_array(1, $collection->cardIds($targetId), true) && $collection->cardIds($me) === [2]);
    $tools->deleteTestAccount('test_cible');
    check('Labo : compte de test inconnu refusé', raises(fn () => $tools->targetId($me, 'test_cible'), DomainException::class));
    $tools->collection($me, 'all');
    $tools->collection($me, 'except', 2);
    $tools->shinies($me, true);
    check('Labo : shiny équipés partout', count($collection->equippedShinyIds($me)) === 58);
    $tools->shinies($me, false);
    check('Labo : shiny retirés', $collection->shinyIds($me) === []);
    $tools->collection($me, 'none');
    check('Labo : collection vidée', $collection->cardIds($me) === []);
    check('Labo : mode de collection inconnu refusé', raises(fn () => $tools->collection($me, 'zzz'), InvalidArgumentException::class));

    // ---- Parties fabriquées
    $options = ['difficulty' => 'easy', 'player_count' => 18, 'ai_count' => 2, 'round' => 18, 'leader' => 'ai', 'pot' => 2, 'seed' => 42, 'player_top' => [5, 6], 'ai_top' => [7]];
    $tools->setupGame($me, $options);
    $state = Session::get('game');
    check('Labo : piles aux tailles demandées', count($state['player']) === 18 && count($state['ai']) === 2 && count($state['pot']) === 2 && $state['round'] === 18);
    check('Labo : cartes imposées en haut des piles', array_slice($state['player'], 0, 2) === [5, 6] && $state['ai'][0] === 7);
    check("Labo : l'ordinateur meneur a déjà annoncé", $state['leader'] === 'ai' && $state['announced'] !== null && $state['user_id'] === $me);

    // Même graine = même partie, même réponse de l'ordinateur
    $replay = function (int $seed) use ($tools, $game, $me): array {
        $tools->setupGame($me, ['difficulty' => 'easy', 'player_count' => 10, 'ai_count' => 10, 'round' => 0, 'leader' => 'player', 'pot' => 0, 'seed' => $seed, 'player_top' => [], 'ai_top' => []]);
        $before = Session::get('game');
        ['result' => $result] = $game->play($me, 0, 'power');

        return [$before['player'], $before['ai'], $result['ai_card']['id']];
    };
    check('Labo : même graine = mêmes piles et même réponse de l\'ordinateur', $replay(7) === $replay(7));
    $differs = false;
    foreach ([8, 9, 10, 11] as $other) {
        $differs = $differs || $replay($other) !== $replay(7);
    }
    check('Labo : une autre graine change la partie', $differs);

    check('Labo : trop de cartes imposées refusé', raises(fn () => $tools->setupGame($me, [...$options, 'player_count' => 1, 'player_top' => [1, 2]]), DomainException::class));
    check('Labo : carte imposée inconnue refusée', raises(fn () => $tools->setupGame($me, [...$options, 'player_top' => [9999]]), InvalidArgumentException::class));
    check('Labo : manche hors limites refusée', raises(fn () => $tools->setupGame($me, [...$options, 'round' => 20]), InvalidArgumentException::class));
    check('Labo : plus de 60 cartes possible (exemplaires répétés)', is_string($tools->setupGame($me, [...$options, 'player_count' => 60, 'ai_count' => 60, 'pot' => 10, 'player_top' => [], 'ai_top' => [], 'round' => 0, 'leader' => 'player'])));

    // ---- Scénarios de fin : l'issue est garantie, quelle que soit la carte ou l'attribut joué
    $expected = [
        'ko_win'     => ['win', 'ai', 0],
        'ko_lose'    => ['lose', 'player', 0],
        'limit_win'  => ['win', null, null],
        'limit_lose' => ['lose', null, null],
        'limit_draw' => ['draw', null, null],
    ];
    $scenarioOk = true;
    $detail = '';
    foreach ($expected as $name => [$status, $emptySide, $emptyCount]) {
        foreach (['easy', 'normal', 'hard'] as $difficulty) {
            foreach ($keys as $attribute) {
                foreach ([0, 1] as $handIndex) {
                    $tools->scenario($me, $name, $difficulty);
                    $current = Session::get('game');
                    if ($handIndex >= count(array_slice($current['player'], 0, 2))) {
                        continue; // main d'une seule carte
                    }
                    ['game' => $view] = $game->play($me, $handIndex, $current['leader'] === 'player' ? $attribute : null);
                    $good = $view['status'] === $status && ($emptySide === null || $view[$emptySide . '_count'] === $emptyCount);
                    $scenarioOk = $scenarioOk && $good;
                    $detail = $good ? $detail : "{$name}/{$difficulty}/{$attribute}/{$handIndex} → {$view['status']}";
                }
            }
        }
    }
    check('Labo : chaque scénario de fin donne le résultat annoncé (toutes difficultés, cartes et attributs)' . ($detail === '' ? '' : " ({$detail})"), $scenarioOk);
    $tools->scenario($me, 'limit_win', 'normal');
    $last = Session::get('game');
    check('Labo : scénario « fin aux manches » = une seule manche restante, cagnotte vide', $last['round'] === 19 && $last['pot'] === [] && $last['leader'] === 'ai' && $last['announced'] !== null);
    check('Labo : scénario inconnu refusé', raises(fn () => $tools->scenario($me, 'zzz', 'easy'), InvalidArgumentException::class));
    $game->abandon($me); // la partie du scénario est comptée comme une défaite : on repart propre pour la suite

    // ---- Fin forcée : passe par le vrai chemin (score, récompense)
    $tools->clearBoosters($me); // les scénarios ci-dessus ont gagné des parties : on repart d'un stock vide
    $scoresBefore = count($scores->history($me));
    $tools->finishGame($me, 'win', 'normal');
    check('Labo : victoire forcée = score enregistré + booster de récompense', count($scores->history($me)) === $scoresBefore + 1 && count($stock->stock($me)) === 1 && $stock->stock($me)[0]['difficulty'] === 'normal');
    // Avec une partie en cours, sa difficulté prime sur celle du formulaire
    $tools->setupGame($me, [...$options, 'difficulty' => 'easy', 'round' => 3, 'leader' => 'player']);
    $tools->finishGame($me, 'lose', 'hard');
    $afterLose = $scores->history($me);
    check('Labo : la partie en cours garde sa propre difficulté (facile), pas celle du formulaire', $afterLose[count($afterLose) - 1]['difficulty'] === 'easy');
    check('Labo : plus de partie en cours après la fin forcée', $game->current($me) === null);
    $tools->finishGame($me, 'lose', 'hard');
    $history = $scores->history($me);
    check('Labo : sans partie en cours, une partie neutre est créée à la difficulté choisie (défaite : pas de booster en plus)', count($stock->stock($me)) === 1 && $history[count($history) - 1] === ['difficulty' => 'hard', 'result' => 'lose']);
    check('Labo : résultat inconnu refusé', raises(fn () => $tools->finishGame($me, 'zzz', 'easy'), InvalidArgumentException::class));

    // ---- Historique et succès
    $tools->clearScores($me);
    $tools->fakeGames($me, 3, 2, 'hard');
    check('Labo : historique factice ajouté', count($scores->history($me)) === 5);
    $tools->achievements($me, true);
    check('Labo : tous les succès débloqués', count($achModel->unlocked($me)) === count(Config::get('achievements.list')));
    $tools->achievements($me, false);
    check('Labo : succès retirés', $achModel->unlocked($me) === []);

    // ---- Comptes de test
    $tools->createTestAccount('vet', 'veteran');
    $vet = $users->findByUsername('test_vet');
    check('Labo : compte de test créé avec mot de passe connu', $vet !== null && password_verify('pw-test-1234', $vet['password_hash']));
    check('Labo : profil vétéran = 60 cartes, historique, succès', count($collection->cardIds((int) $vet['id'])) === 60 && count($scores->history((int) $vet['id'])) === 25 && count($achModel->unlocked((int) $vet['id'])) > 0);
    $tools->createTestAccount('new', 'rookie');
    check('Labo : profil débutant = cartes de départ', count($collection->cardIds((int) $users->findByUsername('test_new')['id'])) === 12);
    $tools->createTestAccount('alm', 'almost');
    check('Labo : profil presque complet = 58 cartes, 4 points', count($collection->cardIds((int) $users->findByUsername('test_alm')['id'])) === 58 && $users->shinyPoints((int) $users->findByUsername('test_alm')['id']) === 4);
    check('Labo : doublon refusé', raises(fn () => $tools->createTestAccount('vet', 'rookie'), DomainException::class));
    check('Labo : nom invalide refusé', raises(fn () => $tools->createTestAccount('a b!', 'rookie'), InvalidArgumentException::class) && raises(fn () => $tools->createTestAccount('ok', 'inconnu'), InvalidArgumentException::class));
    check('Labo : un vrai compte ne peut pas être supprimé', raises(fn () => $tools->deleteTestAccount('exo'), DomainException::class) && $users->findByUsername('exo') !== null);
    $tools->deleteTestAccount('test_vet');
    check('Labo : compte de test supprimé avec ses données', $users->findByUsername('test_vet') === null && $scores->history((int) $vet['id']) === []);
    $tools->deleteAllTestAccounts();
    check('Labo : « tout supprimer » ne touche que les comptes de test', $tools->testAccounts() === [] && $users->findByUsername('exo') !== null);
})();
