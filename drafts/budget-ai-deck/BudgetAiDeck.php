<?php

declare(strict_types=1);

namespace Drafts;

/**
 * IDÉE MISE DE CÔTÉ (non branchée, hors de app/ donc jamais chargée par le jeu).
 *
 * Deck de l'ordinateur calibré sur la force du deck du joueur, selon la difficulté, au lieu de copier ses raretés :
 * le total des attributs de l'ordinateur vise `ratio × total du joueur` (ex. facile 0,85 ; normal 1 ; difficile 1,15).
 * Les raretés ne sont plus imposées, et l'ordinateur évite les cartes du joueur tant qu'il y a assez de choix.
 *
 * Pour l'activer plus tard : voir README.md de ce dossier.
 */
final class BudgetAiDeck
{
    private const PICK_AMONG = 3; // on tire au hasard parmi les N cartes les plus proches du besoin : parties variées

    /**
     * @param array<int, array<string, mixed>> $cards         toutes les cartes, indexées par id
     * @param string[]                         $attributeKeys colonnes à additionner
     * @param array<string, float>             $ratios        difficulté => part de la force du joueur visée
     */
    public function __construct(private array $cards, private array $attributeKeys, private array $ratios)
    {
    }

    /**
     * @param int[] $playerIds
     * @return int[] $size ids de cartes
     */
    public function build(array $playerIds, string $difficulty, int $size): array
    {
        $target = $this->ratios[$difficulty] * array_sum(array_map($this->strength(...), $playerIds));

        $pool = array_values(array_diff(array_keys($this->cards), $playerIds));
        if (count($pool) < $size) {
            $pool = array_keys($this->cards); // pas assez de cartes différentes : on autorise les doublons avec le joueur
        }

        $deck = [];
        for ($slots = $size; $slots > 0; $slots--) {
            $ideal = ($target - array_sum(array_map($this->strength(...), $deck))) / $slots;
            usort($pool, fn (int $a, int $b): int => abs($this->strength($a) - $ideal) <=> abs($this->strength($b) - $ideal));
            $pick = $pool[random_int(0, min(self::PICK_AMONG, count($pool)) - 1)];
            $deck[] = $pick;
            $pool = array_values(array_diff($pool, [$pick]));
        }
        shuffle($deck);

        return $deck;
    }

    private function strength(int $id): int
    {
        return array_sum(array_intersect_key($this->cards[$id], array_flip($this->attributeKeys)));
    }
}
