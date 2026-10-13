<?php

declare(strict_types=1);

namespace App\Services\Boosters;

use App\Models\BoosterModel;
use App\Models\CollectionModel;
use App\Models\UserModel;
use Closure;
use DomainException;

/**
 * Boosters et shiny. Les règles (voir config/game.php) :
 *  - une victoire donne un booster de la difficulté jouée ; il ne tire que des cartes que le joueur n'a pas ;
 *  - quand le joueur a déjà toutes les cartes qu'un booster peut donner, il n'en reçoit plus :
 *    Facile → 1 point shiny ; Normal / Difficile → un booster shiny ;
 *  - un booster shiny contient 0 ou 1 skin shiny (sinon des points) ; les points s'utilisent seulement collection complète.
 */
final class Boosters
{
    public const STANDARD = 'standard';
    public const SHINY = 'shiny';

    /**
     * @param array<int, array<string, mixed>> $cards toutes les cartes, indexées par id
     * @param array<string, array{cards: int, rarities: string[]|null, boost?: array<string, float>, max?: array<string, int>}> $boosters contenu par difficulté
     * @param array<string, mixed> $shiny réglages shiny
     * @param (Closure(int): int)|null $chance tirage entre 1 et le maximum donné (remplaçable dans les tests)
     */
    public function __construct(
        private BoosterModel $stock,
        private CollectionModel $collection,
        private UserModel $users,
        private Rewards $rewards,
        private array $cards,
        private array $boosters,
        private array $shiny,
        private ?Closure $chance = null,
    ) {
    }

    /**
     * Récompense d'une victoire.
     *
     * @return array{type: string, difficulty: string, points: int} type : booster, shiny_booster ou finished (collection et skins complets)
     */
    public function awardForWin(int $userId, string $difficulty): array
    {
        if ($this->isFinished($userId)) { // plus rien à gagner : ni carte, ni skin
            return ['type' => 'finished', 'difficulty' => $difficulty, 'points' => 0];
        }
        $owned = $this->collection->cardIds($userId);

        if ($this->isComplete($difficulty, $owned)) {
            $this->stock->add($userId, self::SHINY, $difficulty);

            return ['type' => 'shiny_booster', 'difficulty' => $difficulty, 'points' => 0];
        }

        $this->stock->add($userId, self::STANDARD, $difficulty);

        return ['type' => 'booster', 'difficulty' => $difficulty, 'points' => 0];
    }

    /**
     * Ouvre un booster du stock.
     *
     * @return array{kind: string, difficulty: string, cards: array<int, array<string, mixed>>, shiny: bool, points: int, converted: int}
     */
    public function open(int $userId, int $boosterId): array
    {
        $booster = $this->stock->take($userId, $boosterId) ?? throw new DomainException('Booster introuvable.');
        $result = $booster['kind'] === self::SHINY ? $this->openShiny($userId, $booster['difficulty']) : $this->openStandard($userId, $booster['difficulty']);
        $result['converted'] = $this->convertObsolete($userId);

        return ['kind' => $booster['kind'], 'difficulty' => $booster['difficulty']] + $result;
    }

    /** Achète un booster shiny avec les points : seulement quand toutes les cartes sont possédées. */
    public function buyShiny(int $userId): void
    {
        if ($this->isFinished($userId)) {
            throw new DomainException('Tu as déjà toutes les cartes du jeu, skins shiny compris.');
        }
        if (!$this->isComplete(null, $this->collection->cardIds($userId))) {
            throw new DomainException('Les points shiny se dépensent une fois toutes les cartes obtenues.');
        }
        if (!$this->users->spendShinyPoints($userId, $this->shiny['cost'])) {
            throw new DomainException("Il faut {$this->shiny['cost']} points shiny.");
        }
        $this->stock->add($userId, self::SHINY, $this->shiny['purchase_difficulty']);
    }

    /**
     * Stock du joueur, après avoir transformé en points les boosters devenus inutiles.
     *
     * @return array{finished: bool, stock: array<int, array{id: int, kind: string, difficulty: string}>, points: int, cost: int, unlocked: bool, converted: int}
     */
    public function overview(int $userId): array
    {
        $finished = $this->isFinished($userId);
        $converted = $finished ? $this->discardStock($userId) : $this->convertObsolete($userId);

        return [
            'finished'  => $finished,
            'stock'     => $this->stock->stock($userId),
            'points'    => $this->users->shinyPoints($userId),
            'cost'      => $this->shiny['cost'],
            'unlocked'  => $this->isComplete(null, $this->collection->cardIds($userId)),
            'converted' => $converted,
        ];
    }

    /** Vrai quand le joueur possède toutes les cartes du jeu ET leur skin shiny : plus aucun booster n'a de sens. */
    public function isFinished(int $userId): bool
    {
        return $this->isComplete(null, $this->collection->cardIds($userId))
            && array_diff(array_keys($this->cards), $this->collection->shinyIds($userId)) === [];
    }

    /** Collection terminée : les boosters restants ne peuvent plus rien donner, ils disparaissent (sans points, qui ne serviraient plus). */
    private function discardStock(int $userId): int
    {
        foreach ($this->stock->stock($userId) as $booster) {
            $this->stock->take($userId, $booster['id']);
        }

        return 0;
    }

    /**
     * Vrai quand le joueur possède toutes les cartes que ce booster peut donner (null = toutes les cartes du jeu).
     *
     * @param int[] $ownedIds
     */
    public function isComplete(?string $difficulty, array $ownedIds): bool
    {
        return array_diff(array_keys($this->poolFor($difficulty)), $ownedIds) === [];
    }

    /** @return array{cards: array<int, array<string, mixed>>, shiny: bool, points: int} */
    private function openStandard(int $userId, string $difficulty): array
    {
        $pool = $this->poolFor($difficulty);
        $owned = $this->collection->cardIds($userId);
        $won = [];
        $points = 0;

        for ($i = 0; $i < $this->boosters[$difficulty]['cards']; $i++) {
            $id = $this->pickWithin($pool, $owned, $won, $difficulty);
            if ($id === null) { // plus rien à tirer : la carte manquante devient des points
                $points += $this->shiny['leftover_points'];
                continue;
            }
            $this->collection->add($userId, $id);
            $owned[] = $id;
            $won[] = $this->cards[$id];
        }
        if ($points > 0) {
            $this->users->addShinyPoints($userId, $points);
        }

        return ['cards' => $won, 'shiny' => false, 'points' => $points];
    }

    /**
     * Tire une carte en respectant le maximum par rareté du booster (config « max ») ; si ce maximum ne laisse plus rien à tirer, il est levé.
     *
     * @param array<int, array<string, mixed>> $pool
     * @param int[] $owned
     * @param array<int, array<string, mixed>> $won cartes déjà tirées dans ce booster
     */
    private function pickWithin(array $pool, array $owned, array $won, string $difficulty): ?int
    {
        $boost = $this->boosters[$difficulty]['boost'] ?? [];
        $counts = array_count_values(array_column($won, 'rarity'));
        $full = array_keys(array_filter($this->boosters[$difficulty]['max'] ?? [], fn (int $max, string $rarity): bool => ($counts[$rarity] ?? 0) >= $max, ARRAY_FILTER_USE_BOTH));
        $allowed = array_filter($pool, fn (array $card): bool => !in_array($card['rarity'], $full, true));

        return $this->rewards->pick($allowed, $owned, $boost) ?? $this->rewards->pick($pool, $owned, $boost);
    }

    /** @return array{cards: array<int, array<string, mixed>>, shiny: bool, points: int} */
    private function openShiny(int $userId, string $difficulty): array
    {
        $roll = ($this->chance ?? fn (int $max): int => random_int(1, $max))(100);
        $id = $roll <= ($this->shiny['chance'][$difficulty] ?? 0) ? $this->pickShiny($userId, $difficulty) : null;

        if ($id !== null) {
            $this->collection->unlockShiny($userId, $id);

            return ['cards' => [$this->cards[$id]], 'shiny' => true, 'points' => 0];
        }

        $this->users->addShinyPoints($userId, $this->shiny['miss_points']);

        return ['cards' => [], 'shiny' => false, 'points' => $this->shiny['miss_points']];
    }

    /** Une carte possédée dont le skin n'est pas encore débloqué, tirée comme les récompenses, avec le boost de rareté de la difficulté (config shiny.rarity_boost). */
    private function pickShiny(int $userId, string $difficulty): ?int
    {
        $ownedCards = array_intersect_key($this->cards, array_flip($this->collection->cardIds($userId)));

        return $this->rewards->pick($ownedCards, $this->collection->shinyIds($userId), $this->shiny['rarity_boost'][$difficulty] ?? []);
    }

    /**
     * Les boosters en stock dont tout le contenu est déjà possédé ne servent plus : ils deviennent des points shiny.
     *
     * @return int nombre de boosters transformés
     */
    private function convertObsolete(int $userId): int
    {
        $owned = $this->collection->cardIds($userId);
        $converted = 0;

        foreach ($this->stock->stock($userId) as $booster) {
            if ($booster['kind'] !== self::STANDARD || !$this->isComplete($booster['difficulty'], $owned)) {
                continue;
            }
            if ($this->stock->take($userId, $booster['id']) !== null) {
                $this->users->addShinyPoints($userId, $this->shiny['obsolete_points']);
                $converted++;
            }
        }

        return $converted;
    }

    /** @return array<int, array<string, mixed>> les cartes qu'un booster de cette difficulté peut donner */
    private function poolFor(?string $difficulty): array
    {
        $rarities = $difficulty === null ? null : ($this->boosters[$difficulty]['rarities'] ?? null);

        return $rarities === null ? $this->cards : array_filter($this->cards, fn (array $card): bool => in_array($card['rarity'], $rarities, true));
    }
}
