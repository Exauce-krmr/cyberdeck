<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\CollectionModel;
use App\Models\ScoreModel;
use App\Services\Achievements\AchievementService;
use App\Services\Boosters\Boosters;
use DomainException;
use InvalidArgumentException;

/** Relie le moteur de règles à la session (partie en cours) et à la base (collection, scores). */
final class GameService
{
    private GameSlot $slot;
    private GameView $view;
    private GameSummary $summary;

    /**
     * @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id
     * @param string[] $difficulties difficultés autorisées
     * @param array<string, int> $deckLimits cartes maximum par rareté dans un deck (voir config/game.php)
     */
    public function __construct(
        private GameEngine $engine,
        private CollectionModel $collection,
        private ScoreModel $scores,
        private Boosters $boosters,
        private AiDeck $aiDeck,
        private Abilities $abilities,
        private array $cards,
        private int $deckSize,
        private array $difficulties,
        private AchievementService $achievements,
        private array $deckLimits = [],
    ) {
        $this->slot = new GameSlot();
        $this->view = new GameView($engine, new GameSummary($cards), $cards);
        $this->summary = new GameSummary($cards);
    }

    /**
     * @param int[] $deckIds les cartes choisies par le joueur, parmi sa collection
     * @return array<string, mixed>
     */
    public function start(int $userId, string $difficulty, array $deckIds): array
    {
        if (!in_array($difficulty, $this->difficulties, true)) {
            throw new InvalidArgumentException('Difficulté inconnue.');
        }
        $deckIds = array_map('intval', $deckIds);
        if (count($deckIds) !== $this->deckSize || count(array_unique($deckIds)) !== $this->deckSize) {
            throw new DomainException("Choisis exactement {$this->deckSize} cartes différentes.");
        }
        if (array_diff($deckIds, $this->collection->cardIds($userId)) !== []) {
            throw new DomainException('Ton deck contient une carte que tu ne possèdes pas.');
        }

        $this->assertDeckLimits($deckIds);

        $this->forfeit($userId); // recommencer ne doit pas effacer une défaite en cours
        shuffle($deckIds);
        $state = $this->engine->start($deckIds, $this->aiDeck->matching($deckIds), $difficulty, $this->abilities->initial());
        $this->slot->save($userId, $state);

        return $this->view->of($state);
    }

    /** @param int[] $deckIds */
    private function assertDeckLimits(array $deckIds): void
    {
        $counts = array_count_values(array_map(fn (int $id): string => (string) ($this->cards[$id]['rarity'] ?? ''), $deckIds));
        foreach ($this->deckLimits as $rarity => $max) {
            if (($counts[$rarity] ?? 0) > $max) {
                throw new DomainException("Un deck contient au plus {$max} cartes de rareté « {$rarity} ».");
            }
        }
    }

    /** @return array<string, mixed> */
    public function useAbility(int $userId, string $name, ?string $attribute): array
    {
        $state = $this->stateFor($userId) ?? throw new DomainException('Aucune partie en cours.');
        $state = $this->abilities->use($state, $name, $attribute);
        $this->slot->save($userId, $state);

        return $this->view->of($state);
    }

    /** Abandonner compte comme une défaite (sinon on quitterait toute partie mal engagée) ; la partie disparaît. */
    public function abandon(int $userId): void
    {
        if (!$this->forfeit($userId)) {
            throw new DomainException('Aucune partie à abandonner.');
        }
    }

    /**
     * Défaite pour une partie commencée mais pas finie : abandon, déconnexion ou nouvelle partie lancée par-dessus.
     *
     * @return bool false s'il n'y avait pas de partie en cours
     */
    public function forfeit(int $userId): bool
    {
        $state = $this->stateFor($userId);
        if ($state === null || $state['status'] !== 'playing') {
            return false;
        }
        $this->scores->record($userId, $state['difficulty'], 'lose', $state['round']);
        $this->slot->clear();

        return true;
    }

    /** @return array<int, array<string, mixed>> les cartes que le joueur possède, parmi lesquelles il compose son deck */
    public function ownedCards(int $userId): array
    {
        return array_intersect_key($this->cards, array_flip($this->collection->cardIds($userId)));
    }

    /**
     * La partie à reprendre. Une partie terminée n'est montrée qu'une fois, juste après la dernière manche (voir play()) :
     * quand on revient plus tard, on repart sur un écran de préparation.
     *
     * @return array<string, mixed>|null null s'il n'y a pas de partie en cours pour ce joueur
     */
    public function current(int $userId): ?array
    {
        $state = $this->stateFor($userId);
        if ($state !== null && $state['status'] !== 'playing') {
            $this->slot->clear();
            $state = null;
        }

        return $state === null ? null : $this->view->of($state);
    }

    /** @return array{result: array<string, mixed>, game: array<string, mixed>} */
    public function play(int $userId, int $handIndex, ?string $attribute): array
    {
        $state = $this->stateFor($userId) ?? throw new DomainException('Aucune partie en cours.');

        if (isset($state['seed'])) {
            mt_srand($state['seed'] + $state['round']); // partie du Labo : les choix aléatoires de l'ordinateur se rejouent à l'identique
        }
        [$state, $result] = $this->engine->play($state, $handIndex, $attribute);
        if ($state['status'] !== 'playing') {
            $state = $this->finish($userId, $state);
        }
        $this->slot->save($userId, $state);

        return ['result' => $result, 'game' => $this->view->of($state)];
    }

    /**
     * Appelé une seule fois par partie : le moteur refuse de jouer une partie terminée.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function finish(int $userId, array $state): array
    {
        $this->scores->record($userId, $state['difficulty'], $state['status'], $state['round']);

        if ($state['status'] === 'win') {
            $state['reward'] = $this->boosters->awardForWin($userId, $state['difficulty']);
        }

        $state['unlocked'] = $this->achievements->afterGame($userId, [
            'status'         => $state['status'],
            'round'          => $state['round'],
            'deck'           => $state['deck'],
            'abilities_used' => $this->abilitiesUsed($state),
            'summary'        => $this->summary->of($state),
        ]);

        return $state;
    }

    /** @return array<string, mixed>|null */
    private function stateFor(int $userId): ?array
    {
        return $this->slot->load($userId);
    }

    /**
     * Utilisations de chaque capacité pendant la partie : celles prévues au départ moins celles qui restent.
     *
     * @param array<string, mixed> $state
     * @return array<string, int>
     */
    private function abilitiesUsed(array $state): array
    {
        $used = [];
        foreach ($this->abilities->initial() as $name => $total) {
            $used[$name] = $total - $state['abilities'][$name];
        }

        return $used;
    }

    /** @return int[] cartes dont le joueur a équipé le skin shiny */
    public function equippedShinies(int $userId): array
    {
        return $this->collection->equippedShinyIds($userId);
    }

    /**
     * Outil de développement (Labo) : installe un état de partie fabriqué à la main. Seul DevTools l'appelle, derrière DevAccess.
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public function install(int $userId, array $state): array
    {
        $this->forfeit($userId);
        $this->slot->save($userId, $state);

        return $this->view->of($state);
    }

    /**
     * Outil de développement (Labo) : termine la partie en cours avec le résultat voulu, par le même chemin qu'une vraie fin
     * (score, récompense, succès).
     *
     * @return array<string, mixed> l'état final, dont `reward` et `unlocked`
     */
    public function conclude(int $userId, string $status): array
    {
        if (!in_array($status, ['win', 'lose', 'draw'], true)) {
            throw new InvalidArgumentException('Résultat inconnu.');
        }
        $state = $this->stateFor($userId);
        if ($state === null || $state['status'] !== 'playing') {
            throw new DomainException('Aucune partie en cours à terminer.');
        }
        $state['status'] = $status;
        $state = $this->finish($userId, $state);
        $this->slot->clear(); // la partie est déjà comptée : rien ne reste à reprendre

        return $state;
    }
}
