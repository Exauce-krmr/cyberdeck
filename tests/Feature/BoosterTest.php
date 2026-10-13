<?php

declare(strict_types=1);

// Boosters et shiny : base SQLite en mémoire. Les tirages sont rendus déterministes avec un « dé » remplaçable.

use App\Core\Config;
use App\Models\BoosterModel;
use App\Models\CardModel;
use App\Models\CollectionModel;
use App\Models\UserModel;
use App\Services\Boosters\Boosters;
use App\Services\Boosters\Rewards;

(function (): void {
    $keys = array_keys(Config::get('game.attributes'));
    $pdo = testDatabase();
    $cards = (new CardModel($pdo, $keys))->all();
    $users = new UserModel($pdo);
    $collection = new CollectionModel($pdo);
    $stock = new BoosterModel($pdo);
    $weights = array_map(fn (array $r): int => $r['weight'], Config::get('game.rarities'));
    $shinyConfig = Config::get('game.shiny');

    $dice = 100; // valeur du « dé » des boosters shiny : 1 = toujours gagné, 100 = toujours raté
    $boosters = new Boosters($stock, $collection, $users, new Rewards($weights), $cards, Config::get('game.boosters'), $shinyConfig, function (int $max) use (&$dice): int {
        return $dice;
    });

    $newUser = function (array $cardIds) use ($users, $collection): int {
        $id = $users->create('u' . random_int(1000, 999999), 'x');
        foreach ($cardIds as $cardId) {
            $collection->add($id, $cardId);
        }

        return $id;
    };
    $idsOf = fn (callable $filter): array => array_keys(array_filter($cards, $filter));
    $nonLegendary = $idsOf(fn (array $c): bool => $c['rarity'] !== 'legendary');
    $all = array_keys($cards);

    // ---- Victoires : un booster, jamais une carte directe
    $rookie = $newUser(array_slice($nonLegendary, 0, 12));
    $award = $boosters->awardForWin($rookie, 'normal');
    check('victoire : un booster rejoint le stock', $award['type'] === 'booster' && count($stock->stock($rookie)) === 1);
    check('victoire : la collection ne change pas avant l\'ouverture', count($collection->cardIds($rookie)) === 12);

    // ---- Ouverture
    $opened = $boosters->open($rookie, $stock->stock($rookie)[0]['id']);
    check('booster normal : 3 cartes différentes ajoutées à la collection', count($opened['cards']) === 3 && count(array_unique(array_column($opened['cards'], 'id'))) === 3 && count($collection->cardIds($rookie)) === 15 && in_array($opened['cards'][0]['id'], $collection->cardIds($rookie), true));
    check('ouvrir retire le booster du stock', $stock->stock($rookie) === []);
    check('un booster déjà ouvert ne peut pas être rouvert', raises(fn () => $boosters->open($rookie, 1), DomainException::class));
    $other = $newUser([]);
    $boosters->awardForWin($other, 'normal');
    check('le booster d\'un autre joueur ne peut pas être ouvert', raises(fn () => $boosters->open($rookie, $stock->stock($other)[0]['id']), DomainException::class));

    $hardUser = $newUser([]);
    $stock->add($hardUser, 'standard', 'hard');
    $hardOpen = $boosters->open($hardUser, $stock->stock($hardUser)[0]['id']);
    check('booster difficile : au plus 3 communes', count(array_filter($hardOpen['cards'], fn (array $c): bool => $c['rarity'] === 'common')) <= 3);
    check('booster difficile : 5 cartes différentes', count($hardOpen['cards']) === 5 && count(array_unique(array_column($hardOpen['cards'], 'id'))) === 5);

    $easyUser = $newUser([]);
    $noLegendary = true;
    for ($i = 0; $i < 20; $i++) {
        $stock->add($easyUser, 'standard', 'easy');
        $opened = $boosters->open($easyUser, $stock->stock($easyUser)[0]['id']);
        $noLegendary = $noLegendary && count($opened['cards']) === 2 && !in_array('legendary', array_column($opened['cards'], 'rarity'), true);
    }
    check('le booster facile donne 2 cartes, jamais de légendaire (20 ouvertures)', $noLegendary && count($collection->cardIds($easyUser)) === 40);

    // ---- Boost de rareté : le booster difficile favorise nettement les cartes rares et plus
    $rewards = new Rewards($weights);
    $share = function (array $boost) use ($rewards, $cards): int {
        $count = 0;
        for ($i = 0; $i < 3000; $i++) {
            $count += $cards[$rewards->pick($cards, [], $boost)]['rarity'] === 'legendary' ? 1 : 0;
        }

        return $count;
    };
    check('boost de rareté : la légendaire sort bien plus souvent avec le boost difficile', $share(Config::get('game.boosters.hard.boost')) > 1.5 * $share([]));

    $maxOk = true;
    for ($i = 0; $i < 60; $i++) { // le maximum tient sur beaucoup d'ouvertures
        $capped = $newUser([]);
        $stock->add($capped, 'standard', 'hard');
        $cards5 = $boosters->open($capped, $stock->stock($capped)[0]['id'])['cards'];
        $maxOk = $maxOk && count(array_filter($cards5, fn (array $c): bool => $c['rarity'] === 'common')) <= 3;
    }
    check('booster difficile : jamais plus de 3 communes (60 ouvertures)', $maxOk);

    // ---- Cartes faciles toutes obtenues : plus de booster facile
    $almost = $newUser($nonLegendary);
    $award = $boosters->awardForWin($almost, 'easy');
    check('facile + cartes faciles toutes obtenues : un booster shiny (25 % de chance)', $award['type'] === 'shiny_booster' && $stock->stock($almost)[0]['kind'] === 'shiny' && $users->shinyPoints($almost) === 0);
    check('chances shiny de la config : 25 / 50 / 70 %', $shinyConfig['chance'] === ['easy' => 25, 'normal' => 50, 'hard' => 70]);
    check('en Normal il reste un vrai booster (légendaires manquantes)', $boosters->awardForWin($almost, 'normal')['type'] === 'booster');

    // ---- Stock devenu inutile : transformé en points
    $stale = $newUser($nonLegendary);
    $stock->add($stale, 'standard', 'easy');
    $stock->add($stale, 'standard', 'easy');
    $overview = $boosters->overview($stale);
    check('boosters faciles en stock devenus inutiles : transformés en points shiny', $overview['stock'] === [] && $overview['converted'] === 2 && $overview['points'] === 2 * $shinyConfig['obsolete_points']);

    // ---- Doublons : la carte qui manque devient des points
    $last = $newUser(array_slice($all, 0, 59));
    $stock->add($last, 'standard', 'hard');
    $lastOpen = $boosters->open($last, $stock->stock($last)[0]['id']);
    check('booster difficile avec 1 carte restante : 1 carte + des points pour les 4 autres', count($lastOpen['cards']) === 1 && $lastOpen['points'] === 4 * $shinyConfig['leftover_points']);

    // ---- Collection complète : boosters shiny, uniquement en Normal / Difficile
    $master = $newUser($all);
    check('collection complète, victoire Normal : booster shiny', $boosters->awardForWin($master, 'normal')['type'] === 'shiny_booster');
    check('collection complète, victoire Difficile : booster shiny', $boosters->awardForWin($master, 'hard')['type'] === 'shiny_booster');
    check('collection complète, victoire Facile : booster shiny aussi', $boosters->awardForWin($master, 'easy')['type'] === 'shiny_booster');
    check('trois boosters shiny en stock', count(array_filter($stock->stock($master), fn (array $b): bool => $b['kind'] === 'shiny')) === 3);

    // ---- Ouverture d'un booster shiny
    $dice = 1;
    $hit = $boosters->open($master, $stock->stock($master)[0]['id']);
    $unlocked = $collection->shinyIds($master);
    check('booster shiny gagnant : 1 skin débloqué et équipé', $hit['shiny'] === true && count($hit['cards']) === 1 && $unlocked === [$hit['cards'][0]['id']] && $collection->equippedShinyIds($master) === $unlocked);
    $dice = 100;
    $pointsBefore = $users->shinyPoints($master);
    $miss = $boosters->open($master, $stock->stock($master)[0]['id']);
    check('booster shiny raté : rien, mais des points', $miss['shiny'] === false && $miss['cards'] === [] && $users->shinyPoints($master) === $pointsBefore + $shinyConfig['miss_points']);

    // ---- Points : bloqués tant que la collection n'est pas complète, puis achat
    $locked = $newUser($nonLegendary);
    $users->addShinyPoints($locked, 10);
    check('points shiny bloqués collection incomplète', raises(fn () => $boosters->buyShiny($locked), DomainException::class) && $users->shinyPoints($locked) === 10);
    $poor = $newUser($all);
    check('pas assez de points : refusé', raises(fn () => $boosters->buyShiny($poor), DomainException::class));
    $rich = $newUser($all);
    $users->addShinyPoints($rich, $shinyConfig['cost'] + 2);
    $boosters->buyShiny($rich);
    check('achat : points dépensés et booster shiny en stock', $users->shinyPoints($rich) === 2 && count($stock->stock($rich)) === 1 && $stock->stock($rich)[0]['kind'] === 'shiny');

    // ---- Skin équipé / retiré
    $card = $collection->shinyIds($master)[0];
    check('retirer le skin d\'une carte débloquée', $collection->toggleShiny($master, $card) === true && $collection->equippedShinyIds($master) === []);
    check('le remettre', $collection->toggleShiny($master, $card) === true && $collection->equippedShinyIds($master) === [$card]);
    check('un skin non débloqué ne s\'active pas', $collection->toggleShiny($master, $all[0] === $card ? $all[1] : $all[0]) === false);

    // ---- Tout obtenu (cartes ET skins) : plus de booster
    $done = $newUser($all);
    $collection->setAllShiny($done, true);
    $stock->add($done, 'shiny', 'normal');
    $stock->add($done, 'standard', 'easy');
    $overviewDone = $boosters->overview($done);
    check('tout obtenu : le stock devenu inutile disparaît, sans points', $overviewDone['finished'] === true && $overviewDone['stock'] === [] && $users->shinyPoints($done) === 0);
    check('tout obtenu : une victoire ne donne plus de booster', $boosters->awardForWin($done, 'hard')['type'] === 'finished' && $stock->stock($done) === []);
    check('tout obtenu : acheter un booster shiny est refusé', raises(fn () => $boosters->buyShiny($done), DomainException::class));
    check('il reste des skins à obtenir : pas fini', $boosters->overview($master)['finished'] === false);
})();
