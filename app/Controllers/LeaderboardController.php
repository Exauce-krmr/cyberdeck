<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Http;
use App\Core\View;
use App\Models\ScoreModel;
use App\Services\Achievements\AchievementService;

final class LeaderboardController
{
    private const TOP = 20;

    public function __construct(private View $view, private ScoreModel $scores, private AchievementService $achievements)
    {
    }

    public function index(): void
    {
        $unlocked = $this->achievements->unlockedByUser();
        $rows = [];
        foreach ($this->scores->leaderboard(self::TOP) as $position => $row) {
            $rows[] = $row + ['title_label' => $this->achievements->title($row['title'], $unlocked[$row['id']] ?? [], $position + 1)['label']];
        }

        Http::html($this->view->render('leaderboard.index', ['rows' => $rows, 'difficulties' => Config::get('game.difficulties')]));
    }
}
