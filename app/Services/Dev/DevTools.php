<?php

declare(strict_types=1);

namespace App\Services\Dev;

use App\Models\AchievementModel;
use App\Models\BoosterModel;
use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Models\UserModel;
use App\Services\Boosters\Boosters;
use App\Services\Dev\DevGameBuilder;
use App\Services\Dev\DevGuard;
use App\Services\Dev\DevProfiles;
use App\Services\Game\Abilities;
use App\Services\Game\GameEngine;
use App\Services\Game\GameService;
use DomainException;
use InvalidArgumentException;

/**
 * Les actions du Labo (voir DevAccess pour qui peut les lancer). Chacune agit sur le compte connecté, sauf les comptes de test,
 * et rend une phrase qui dit ce qui s'est passé : le Labo l'affiche telle quelle.
 */
final class DevTools
{
    public const PRESETS = [
        'rookie'    => 'Débutant : cartes de départ et boosters de bienvenue, aucun historique',
        'collector' => 'Collectionneur : 60 cartes, shiny équipés, un stock de boosters',
        'almost'    => 'Presque complet : 58 cartes, 4 points shiny, 2 boosters',
        'veteran'   => 'Vétéran : 60 cartes, 25 parties jouées, tous les succès',
    ];

    /** Scénarios de fin : la partie est installée à une manche de la fin ; c'est le vrai écran qui joue la dernière manche et l'animation. */
    public const SCENARIOS = [
        'ko_win'     => 'Victoire KO : l\'ordinateur tombe à 0 carte (explosion)',
        'ko_lose'    => 'Défaite KO : tu tombes à 0 carte (glitch total)',
        'limit_win'  => 'Fin aux manches : victoire aux cartes (dernière manche perdue)',
        'limit_lose' => 'Fin aux manches : défaite aux cartes (dernière manche gagnée)',
        'limit_draw' => 'Fin aux manches : égalité parfaite',
    ];

    private const MAX_BOOSTERS = 20;
    private const MAX_FAKE_GAMES = 100;
    private const MAX_SHINY_POINTS = 9999;
    private const NEUTRAL_PILE = 10; // partie neutre créée pour finishGame()

    private DevGuard $guard;
    private DevGameBuilder $builder;
    private DevProfiles $profiles;

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
        private GameEngine $engine,
        private GameService $game,
        private Abilities $abilities,
        private array $cards,
        private array $difficulties,
        private array $achievementCodes,
        private int $starterCards,
        private string $testPrefix,
        private string $testPassword,
        private array $welcomeBoosters = [],
    ) {
        $this->guard = new DevGuard($difficulties, $cards);
        $this->builder = new DevGameBuilder($engine, $abilities, $this->guard, $cards);
        $this->profiles = new DevProfiles($users, $collection, $boosters, $scores, $achievements, $cards, $difficulties, $achievementCodes, $starterCards, $welcomeBoosters);
    }

    /** @return array{cards: int, total: int, shinies: int, points: int, boosters: array<string, int>} l'état du compte connecté, affiché en haut du Labo */
    public function snapshot(int $userId): array
    {
        $boosters = [];
        foreach ($this->boosters->stock($userId) as $booster) {
            $label = "{$booster['kind']} · {$booster['difficulty']}";
            $boosters[$label] = ($boosters[$label] ?? 0) + 1;
        }

        return [
            'cards'    => count($this->collection->cardIds($userId)),
            'total'    => count($this->cards),
            'shinies'  => count($this->collection->shinyIds($userId)),
            'points'   => $this->users->shinyPoints($userId),
            'boosters' => $boosters,
        ];
    }

    // ---------------------------------------------------------------- Boosters et points

    /** Le compte visé par une action du Labo : le tien (nom vide) ou un compte de test existant, jamais un vrai compte. */
    public function targetId(int $me, string $username): int
    {
        if ($username === '') {
            return $me;
        }
        if (!str_starts_with($username, $this->testPrefix)) {
            throw new DomainException('Seuls les comptes de test peuvent être visés ici.');
        }
        $user = $this->users->findByUsername($username) ?? throw new DomainException('Compte introuvable.');

        return (int) $user['id'];
    }

    public function giveBoosters(int $userId, string $kind, string $difficulty, int $count): string
    {
        if (!in_array($kind, [Boosters::STANDARD, Boosters::SHINY], true)) {
            throw new InvalidArgumentException('Type de booster inconnu.');
        }
        $this->guard->difficulty($difficulty);
        $count = $this->guard->between($count, 1, self::MAX_BOOSTERS, 'Nombre de boosters');
        for ($i = 0; $i < $count; $i++) {
            $this->boosters->add($userId, $kind, $difficulty);
        }

        return "{$count} booster(s) {$kind} ({$difficulty}) ajouté(s) au stock.";
    }

    public function clearBoosters(int $userId): string
    {
        $this->boosters->clear($userId);

        return 'Stock de boosters vidé.';
    }

    public function setShinyPoints(int $userId, int $points): string
    {
        $this->users->setShinyPoints($userId, $this->guard->between($points, 0, self::MAX_SHINY_POINTS, 'Points shiny'));

        return "Points shiny fixés à {$points}.";
    }

    // ---------------------------------------------------------------- Collection

    /** @param string $mode all | none | except (toutes sauf $missing cartes tirées au hasard) */
    public function collection(int $userId, string $mode, int $missing = 0): string
    {
        $ids = array_keys($this->cards);
        if ($mode === 'none') {
            $this->collection->clear($userId);

            return 'Collection vidée.';
        }
        if ($mode === 'except') {
            $missing = $this->guard->between($missing, 1, count($ids) - 1, 'Cartes manquantes');
            shuffle($ids);
            $ids = array_slice($ids, $missing);
        } elseif ($mode !== 'all') {
            throw new InvalidArgumentException('Mode de collection inconnu.');
        }

        $this->collection->clear($userId);
        foreach ($ids as $id) {
            $this->collection->add($userId, $id);
        }

        return count($ids) . ' carte(s) dans la collection.';
    }

    /** @param int[] $cardIds */
    public function giveCards(int $userId, array $cardIds): string
    {
        $ids = $this->nonEmptyCards($cardIds);
        foreach ($ids as $id) {
            $this->collection->add($userId, $id);
        }

        return count($ids) . ' carte(s) donnée(s).';
    }

    /** @param int[] $cardIds */
    public function removeCards(int $userId, array $cardIds): string
    {
        $ids = $this->nonEmptyCards($cardIds);
        foreach ($ids as $id) {
            $this->collection->remove($userId, $id);
        }

        return count($ids) . ' carte(s) retirée(s).';
    }

    /**
     * @param int[] $cardIds
     * @return int[]
     */
    private function nonEmptyCards(array $cardIds): array
    {
        $ids = $this->guard->cardIds($cardIds);
        if ($ids === []) {
            throw new InvalidArgumentException('Choisis au moins une carte.');
        }

        return $ids;
    }

    public function shinies(int $userId, bool $on): string
    {
        $this->collection->setAllShiny($userId, $on);

        return $on ? 'Skins shiny débloqués et équipés sur toutes tes cartes.' : 'Skins shiny retirés.';
    }

    // ---------------------------------------------------------------- Parties

    /**
     * Installe une partie fabriquée (voir DevGameBuilder::build pour les options).
     *
     * @param array{difficulty: string, player_count: int, ai_count: int, round: int, leader: string, pot: int, seed: ?int, player_top: int[], ai_top: int[]} $options
     */
    public function setupGame(int $userId, array $options): string
    {
        $state = $this->builder->build($options);
        $this->game->install($userId, $state);

        $seed = $options['seed'];
        $suffix = $seed === null ? '' : " Graine {$seed}.";

        return "Partie installée : toi {$options['player_count']} carte(s), ordinateur {$options['ai_count']}, manche " . ($options['round'] + 1) . "/{$this->engine->maxRounds()}, difficulté {$options['difficulty']}.{$suffix} Ouvre la page Jouer.";
    }

    /**
     * Installe une partie qui se termine à la prochaine manche, quoi que tu joues : les cartes en main de l'un battent celles de l'autre
     * sur tous les attributs. Lance ensuite la manche sur la page Jouer pour voir la vraie séquence de fin.
     */
    public function scenario(int $userId, string $name, string $difficulty): string
    {
        if (!isset(self::SCENARIOS[$name])) {
            throw new InvalidArgumentException('Scénario inconnu.');
        }
        $this->setupGame($userId, $this->builder->scenario($name) + ['difficulty' => $difficulty, 'pot' => 0, 'seed' => null]);

        return self::SCENARIOS[$name] . ' : joue la manche qui reste.';
    }

    /** Termine la partie en cours (ou une partie neutre créée pour l'occasion) avec le résultat voulu : score, récompense et succès comptent. */
    public function finishGame(int $userId, string $status, string $difficulty): string
    {
        $this->guard->difficulty($difficulty);
        if ($this->game->current($userId) === null) {
            $this->setupGame($userId, ['difficulty' => $difficulty, 'player_count' => self::NEUTRAL_PILE, 'ai_count' => self::NEUTRAL_PILE, 'round' => 0, 'leader' => GameEngine::PLAYER, 'pot' => 0, 'seed' => null, 'player_top' => [], 'ai_top' => []]);
        }
        $final = $this->game->conclude($userId, $status);

        $reward = isset($final['reward']) ? " Récompense : {$final['reward']['type']}." : '';
        $unlocked = count($final['unlocked']['achievements'] ?? []);

        return "Partie terminée : {$status} en {$final['difficulty']}.{$reward}" . ($unlocked > 0 ? " {$unlocked} succès débloqué(s)." : '');
    }

    /** Ajoute des parties factices à l'historique (classement, succès de série…). */
    public function fakeGames(int $userId, int $wins, int $losses, string $difficulty): string
    {
        $this->guard->difficulty($difficulty);
        $wins = $this->guard->between($wins, 0, self::MAX_FAKE_GAMES, 'Victoires');
        $losses = $this->guard->between($losses, 0, self::MAX_FAKE_GAMES, 'Défaites');
        for ($i = 0; $i < $wins; $i++) {
            $this->scores->record($userId, $difficulty, 'win', 12);
        }
        for ($i = 0; $i < $losses; $i++) {
            $this->scores->record($userId, $difficulty, 'lose', 20);
        }

        return "{$wins} victoire(s) et {$losses} défaite(s) ajoutées ({$difficulty}).";
    }

    public function clearScores(int $userId): string
    {
        $this->scores->clear($userId);

        return 'Historique de parties effacé.';
    }

    // ---------------------------------------------------------------- Succès

    public function achievements(int $userId, bool $unlock): string
    {
        if (!$unlock) {
            $this->achievements->clear($userId);

            return 'Succès retirés.';
        }
        $this->profiles->unlockAll($userId);

        return count($this->achievementCodes) . ' succès débloqués.';
    }

    // ---------------------------------------------------------------- Comptes de test

    /** @return string[] */
    public function testAccounts(): array
    {
        return $this->users->usernamesWithPrefix($this->testPrefix);
    }

    public function createTestAccount(string $name, string $preset): string
    {
        if (!isset(self::PRESETS[$preset])) {
            throw new InvalidArgumentException('Profil inconnu.');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $name)) {
            throw new InvalidArgumentException('Nom : 1 à 20 caractères, lettres, chiffres, _ ou -.');
        }
        $username = $this->testPrefix . $name;
        if ($this->users->findByUsername($username) !== null) {
            throw new DomainException("Le compte {$username} existe déjà.");
        }

        $id = $this->users->create($username, password_hash($this->testPassword, PASSWORD_DEFAULT));
        $this->profiles->apply($id, $preset);

        return "Compte {$username} créé (mot de passe : {$this->testPassword}).";
    }

    public function deleteTestAccount(string $username): string
    {
        if (!str_starts_with($username, $this->testPrefix)) { // garde-fou : jamais un vrai compte
            throw new DomainException('Seuls les comptes de test peuvent être supprimés ici.');
        }
        $user = $this->users->findByUsername($username) ?? throw new DomainException('Compte introuvable.');
        $this->users->delete((int) $user['id']);

        return "Compte {$username} supprimé.";
    }

    public function deleteAllTestAccounts(): string
    {
        $names = $this->testAccounts();
        foreach ($names as $name) {
            $this->deleteTestAccount($name);
        }

        return count($names) . ' compte(s) de test supprimé(s).';
    }
}
