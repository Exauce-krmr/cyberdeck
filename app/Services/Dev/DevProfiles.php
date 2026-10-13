<?php

declare(strict_types=1);

namespace App\Services\Dev;

use App\Models\AchievementModel;
use App\Models\BoosterModel;
use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Models\UserModel;
use App\Services\Boosters\Boosters;
use InvalidArgumentException;

/** Les profils de départ des comptes de test : qui possède quoi, avec quel historique. */
final class DevProfiles
{
    private const VETERAN_WINS_PER_DIFFICULTY = 5;
    private const VETERAN_LOSSES = 10;

    /**
     * @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id
     * @param string[] $difficulties
     * @param string[] $achievementCodes
     */
    public function __construct(
        private UserModel $users,
        private CollectionModel $collection,
        private BoosterModel $boosters,
        private ScoreModel $scores,
        private AchievementModel $achievements,
        private array $cards,
        private array $difficulties,
        private array $achievementCodes,
        private int $starterCards,
        /** @var array<string, int> boosters offerts à l'inscription (config game.welcome_boosters) : le profil « Débutant » les reçoit comme un vrai nouveau compte */
        private array $welcomeBoosters = [],
    ) {
    }

    /** @param string $preset une clé de DevTools::PRESETS */
    public function apply(int $userId, string $preset): void
    {
        $all = array_keys($this->cards);

        match ($preset) {
            'rookie'    => $this->rookie($userId),
            'collector' => $this->collector($userId, $all),
            'almost'    => $this->almost($userId, $all),
            'veteran'   => $this->veteran($userId, $all),
            default     => throw new InvalidArgumentException('Profil inconnu.'),
        };
    }

    /** @return int[] */
    private function shuffledCommons(): array
    {
        $commons = array_keys(array_filter($this->cards, fn (array $c): bool => $c['rarity'] === 'common'));
        shuffle($commons);

        return $commons;
    }

    /** @param int[] $ids */
    private function rookie(int $userId): void
    {
        $this->giveCards($userId, array_slice($this->shuffledCommons(), 0, $this->starterCards));
        foreach ($this->welcomeBoosters as $difficulty => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->boosters->add($userId, Boosters::STANDARD, $difficulty);
            }
        }
    }

    private function giveCards(int $userId, array $ids): void
    {
        foreach ($ids as $id) {
            $this->collection->add($userId, $id);
        }
    }

    /** @param int[] $all */
    private function collector(int $userId, array $all): void
    {
        $this->giveCards($userId, $all);
        $this->collection->setAllShiny($userId, true);
        foreach ($this->difficulties as $difficulty) {
            $this->boosters->add($userId, Boosters::STANDARD, $difficulty);
        }
        $this->boosters->add($userId, Boosters::SHINY, $this->hardest());
    }

    /** @param int[] $all */
    private function almost(int $userId, array $all): void
    {
        shuffle($all);
        $this->giveCards($userId, array_slice($all, 2));
        $this->users->setShinyPoints($userId, 4);
        $this->boosters->add($userId, Boosters::STANDARD, $this->difficulties[array_key_first($this->difficulties)]);
        $this->boosters->add($userId, Boosters::STANDARD, $this->hardest());
    }

    /** @param int[] $all */
    private function veteran(int $userId, array $all): void
    {
        $this->giveCards($userId, $all);
        foreach ($this->difficulties as $difficulty) {
            for ($i = 0; $i < self::VETERAN_WINS_PER_DIFFICULTY; $i++) {
                $this->scores->record($userId, $difficulty, 'win', 10 + $i);
            }
        }
        for ($i = 0; $i < self::VETERAN_LOSSES; $i++) {
            $this->scores->record($userId, $this->difficulties[$i % count($this->difficulties)], 'lose', 20);
        }
        $this->unlockAll($userId);
    }

    public function unlockAll(int $userId): void
    {
        foreach ($this->achievementCodes as $code) {
            $this->achievements->unlock($userId, $code);
        }
    }

    private function hardest(): string
    {
        return $this->difficulties[array_key_last($this->difficulties)];
    }
}
