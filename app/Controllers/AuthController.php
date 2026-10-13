<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\View;
use App\Services\Auth\AuthService;
use App\Services\Game\GameService;

final class AuthController
{
    public function __construct(private View $view, private AuthService $auth, private GameService $game)
    {
    }

    public function showLogin(): void
    {
        Http::html($this->view->render('auth.login', ['error' => null, 'old' => '']));
    }

    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));

        if ($this->auth->login($username, (string) ($_POST['password'] ?? ''))) {
            Http::redirect('/game');
            return;
        }

        Http::html($this->view->render('auth.login', ['error' => 'Pseudo ou mot de passe incorrect.', 'old' => $username]), 422);
    }

    public function showRegister(): void
    {
        Http::html($this->view->render('auth.register', ['errors' => [], 'old' => '']));
    }

    public function register(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $errors = $this->auth->register($username, (string) ($_POST['password'] ?? ''));

        if ($errors === []) {
            Http::redirect('/game');
            return;
        }

        Http::html($this->view->render('auth.register', ['errors' => $errors, 'old' => $username]), 422);
    }

    public function logout(): void
    {
        $userId = $this->auth->userId();
        if ($userId !== null) {
            $this->game->forfeit($userId); // se déconnecter en pleine partie ne l'efface pas : c'est une défaite
        }
        $this->auth->logout();
        Http::redirect('/login');
    }
}
