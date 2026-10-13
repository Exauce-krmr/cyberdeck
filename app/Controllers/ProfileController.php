<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Http;
use App\Core\View;
use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Services\Achievements\AchievementService;
use App\Services\Auth\AuthService;

/** Page de profil : statistiques, succès et choix du titre affiché. */
final class ProfileController
{
    public function __construct(
        private View $view,
        private AuthService $auth,
        private ScoreModel $scores,
        private AchievementService $achievements,
        private CollectionModel $collection,
        private int $cardCount,
    ) {
    }

    public function index(): void
    {
        $user = $this->auth->user() ?? [];
        $userId = (int) $user['id'];

        Http::html($this->view->render('profile.index', [
            'user'         => $user,
            'stats'        => $this->scores->statsFor($user['username']),
            'overview'     => $this->achievements->overview($userId, $user['title']),
            'owned'        => count($this->collection->cardIds($userId)),
            'cardCount'    => $this->cardCount,
            'difficulties' => Config::get('game.difficulties'),
            'categories'   => Config::get('achievements.categories'),
        ]));
    }

    public function chooseTitle(): void
    {
        $userId = (int) $this->auth->userId();
        $this->achievements->chooseTitle($userId, (string) ($_POST['title'] ?? ''));
        Http::redirect('/profile');
    }
}
