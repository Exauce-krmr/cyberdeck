<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Http;

return function (array $s): void {
    $auth = $s['auth'];

    // Enveloppes réutilisables : chacune vérifie une condition avant d'appeler le contrôleur.
    $protected = fn (callable $next): Closure => fn () => $auth->userId() === null ? Http::redirect('/login') : $next();
    $guest = fn (callable $next): Closure => fn () => $auth->userId() !== null ? Http::redirect('/game') : $next();
    $api = fn (callable $next): Closure => fn () => $auth->userId() === null
        ? Http::json(['error' => 'Connexion requise.'], 401)
        : $next();
    $csrf = fn (callable $next): Closure => fn () => Csrf::isValid(Http::submittedToken())
        ? $next()
        : Http::html('Jeton de sécurité invalide : recharge la page.', 419);

    app()->get('/', fn () => Http::redirect($auth->userId() === null ? '/login' : '/game'));

    app()->get('/login', $guest(fn () => $s['authPages']->showLogin()));
    app()->post('/login', $csrf(fn () => $s['authPages']->login()));
    app()->get('/register', $guest(fn () => $s['authPages']->showRegister()));
    app()->post('/register', $csrf(fn () => $s['authPages']->register()));
    app()->post('/logout', $csrf(fn () => $s['authPages']->logout()));

    app()->get('/game', $protected(fn () => $s['game']->page()));
    app()->get('/api/game/state', $api(fn () => $s['game']->state()));
    app()->post('/api/game/start', $api($csrf(fn () => $s['game']->start())));
    app()->post('/api/game/ability', $api($csrf(fn () => $s['game']->ability())));
    app()->post('/api/game/abandon', $api($csrf(fn () => $s['game']->abandon())));
    app()->post('/api/game/play', $api($csrf(fn () => $s['game']->play())));

    app()->get('/boosters', $protected(fn () => $s['boosters']->index()));
    app()->post('/api/boosters/open', $api($csrf(fn () => $s['boosters']->open())));
    app()->post('/api/boosters/buy-shiny', $api($csrf(fn () => $s['boosters']->buyShiny())));
    app()->post('/api/collection/shiny', $api($csrf(fn () => $s['collection']->toggleShiny())));

    app()->get('/collection', $protected(fn () => $s['collection']->index()));
    app()->get('/profile', $protected(fn () => $s['profile']->index()));
    app()->post('/profile/title', $csrf($protected(fn () => $s['profile']->chooseTitle())));
    // Labo de test : seule la pile (connexion + CSRF) est ici ; l'autorisation (DEV_TOOLS + DEV_USERS) est revérifiée dans le contrôleur.
    app()->get('/dev', $protected(fn () => $s['dev']->index()));
    app()->post('/dev', $csrf($protected(fn () => $s['dev']->act())));

    app()->get('/leaderboard', fn () => $s['leaderboard']->index());
};
