<?php

declare(strict_types=1);

namespace App\Services\Achievements;

use App\Models\AchievementModel;
use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Models\UserModel;
use LogicException;

/** Débloque les succès à la fin d'une partie et gère les titres qu'ils donnent. */
final class AchievementService
{
    /**
     * @param array<string, array<string, mixed>> $definitions succès (config/achievements.php, clé « list »)
     * @param array<string, array<string, mixed>> $titles titres (clé « titles »)
     * @param array<string, string> $categories libellés des catégories de succès
     */
    public function __construct(
        private AchievementModel $achievements,
        private ScoreModel $scores,
        private CollectionModel $collection,
        private UserModel $users,
        private AchievementMetrics $metrics,
        private array $definitions,
        private array $titles,
        private array $categories,
    ) {
    }

    /**
     * Évalue tous les succès après une partie terminée (déjà enregistrée dans les scores).
     *
     * @param array{status: string, round: int, deck: int[], abilities_used: array<string, int>, summary: array<string, mixed>} $game
     * @return array{achievements: list<array{code: string, label: string, description: string}>, titles: string[]} ce qui vient d'être débloqué
     */
    public function afterGame(int $userId, array $game): array
    {
        return $this->evaluate($userId, $game);
    }

    /**
     * Évalue les succès hors partie (un skin shiny vient d'être débloqué, par exemple) : seuls ceux de la collection peuvent changer.
     *
     * @return array{achievements: list<array{code: string, label: string, description: string}>, titles: string[]}
     */
    public function afterCollectionChange(int $userId): array
    {
        return $this->evaluate($userId, null);
    }

    /**
     * @param array{status: string, round: int, deck: int[], abilities_used: array<string, int>, summary: array<string, mixed>}|null $game
     * @return array{achievements: list<array{code: string, label: string, description: string}>, titles: string[]}
     */
    private function evaluate(int $userId, ?array $game): array
    {
        $measures = $this->metrics->compute($this->scores->history($userId), $this->collection->cardIds($userId), $game, $this->collection->shinyIds($userId));
        $already = array_keys($this->achievements->unlocked($userId));

        $fresh = array_values(array_diff($this->reached($measures), $already));
        foreach ($fresh as $code) {
            $this->achievements->unlock($userId, $code);
        }

        // Le rang change avec les autres joueurs : seuls les titres gagnés par ses propres succès sont annoncés.
        $before = $this->availability($already, null);
        $now = $this->availability([...$already, ...$fresh], null);
        $newTitles = array_filter(array_keys($this->titles), fn (string $code): bool => $now[$code] && !$before[$code]);

        return [
            'achievements' => array_map(fn (string $code): array => ['code' => $code] + array_intersect_key($this->definitions[$code], array_flip(['label', 'description'])), $fresh),
            'titles'       => array_values(array_map(fn (string $code): string => $this->titles[$code]['label'], $newTitles)),
        ];
    }

    /**
     * Codes des succès dont la règle est satisfaite par ces mesures.
     *
     * @param array<string, int|null> $measures
     * @return string[]
     */
    public function reached(array $measures): array
    {
        $codes = [];
        foreach ($this->definitions as $code => $rule) {
            $value = $measures[$rule['metric']] ?? null;
            if ($value === null) {
                continue;
            }
            if ((!isset($rule['min']) || $value >= $rule['min']) && (!isset($rule['max']) || $value <= $rule['max'])) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Tout ce que la page de profil affiche : succès (débloqués ou non) et titres (disponibles ou non).
     *
     * @return array{count: int, total: int, achievements: array<string, array<string, mixed>>, titles: array<string, array<string, mixed>>, title: array{code: string, label: string}}
     */
    public function overview(int $userId, ?string $chosenTitle): array
    {
        $unlocked = $this->achievements->unlocked($userId);
        $codes = array_keys($unlocked);
        $available = $this->availability($codes, $this->rankOf($userId));

        $achievements = [];
        foreach ($this->definitions as $code => $rule) {
            $achievements[$code] = $rule + ['unlocked_at' => $unlocked[$code] ?? null];
        }
        $titles = [];
        foreach ($this->titles as $code => $title) {
            $titles[$code] = $title + ['available' => $available[$code], 'requirement' => $this->requirement($title)];
        }

        return [
            'count'        => count($codes),
            'total'        => count($this->definitions),
            'achievements' => $achievements,
            'titles'       => $titles,
            'title'        => $this->resolve($chosenTitle, $available),
        ];
    }

    /**
     * Titre à afficher pour un joueur : celui qu'il a choisi s'il y a droit, sinon le plus haut titre de succès.
     *
     * @param string[] $unlockedCodes ses succès
     * @param int|null $rank sa place au classement (null s'il n'a pas joué)
     * @return array{code: string, label: string}
     */
    public function title(?string $chosen, array $unlockedCodes, ?int $rank): array
    {
        return $this->resolve($chosen, $this->availability($unlockedCodes, $rank));
    }

    /** @return bool false si ce titre n'existe pas ou que le joueur n'y a pas (ou plus) droit */
    public function chooseTitle(int $userId, string $code): bool
    {
        $available = $this->availability(array_keys($this->achievements->unlocked($userId)), $this->rankOf($userId));
        if (!($available[$code] ?? false)) {
            return false;
        }
        $this->users->setTitle($userId, $code);

        return true;
    }

    /** @return array<int, string[]> id du joueur => codes de ses succès */
    public function unlockedByUser(): array
    {
        return $this->achievements->unlockedByUser();
    }

    /**
     * Quels titres le joueur peut porter.
     *
     * @param string[] $unlockedCodes
     * @return array<string, bool> code du titre => disponible
     */
    private function availability(array $unlockedCodes, ?int $rank): array
    {
        $inCategory = array_count_values(array_map(fn (string $code): string => $this->definitions[$code]['category'], $unlockedCodes));

        return array_map(fn (array $title): bool => match ($title['type']) {
            'count'    => count($unlockedCodes) >= $title['min'],
            'category' => ($inCategory[$title['category']] ?? 0) >= $title['min'],
            'rank'     => $rank !== null && $rank <= $title['max'],
            'achievement' => in_array($title['code'], $unlockedCodes, true),
            default    => throw new LogicException("Type de titre inconnu : {$title['type']}"),
        }, $this->titles);
    }

    /**
     * @param array<string, bool> $available
     * @return array{code: string, label: string}
     */
    private function resolve(?string $chosen, array $available): array
    {
        $code = $chosen !== null && ($available[$chosen] ?? false) ? $chosen : $this->defaultTitle($available);

        return ['code' => $code, 'label' => $this->titles[$code]['label']];
    }

    /**
     * Le plus haut titre « au nombre de succès » dont le joueur dispose.
     *
     * @param array<string, bool> $available
     */
    private function defaultTitle(array $available): string
    {
        $best = array_key_first($this->titles);
        foreach ($this->titles as $code => $title) {
            if ($title['type'] === 'count' && $available[$code]) {
                $best = $code;
            }
        }

        return $best;
    }

    /** @param array<string, mixed> $title */
    private function requirement(array $title): string
    {
        return match ($title['type']) {
            'count'    => $title['min'] === 0 ? 'Titre de départ' : $title['min'] . ' succès',
            'category' => $title['min'] . ' succès en ' . $this->categories[$title['category']],
            'rank'     => $title['max'] === 1 ? 'Première place du classement' : 'Dans le top ' . $title['max'] . ' du classement',
            'achievement' => 'Succès « ' . $this->definitions[$title['code']]['label'] . ' » : ' . $this->definitions[$title['code']]['description'],
            default    => throw new LogicException("Type de titre inconnu : {$title['type']}"),
        };
    }

    /** Place du joueur au classement (1 = premier) ; null s'il n'a terminé aucune partie. */
    private function rankOf(int $userId): ?int
    {
        foreach ($this->scores->leaderboard(PHP_INT_MAX) as $position => $row) {
            if ($row['id'] === $userId) {
                return $position + 1;
            }
        }

        return null;
    }
}
