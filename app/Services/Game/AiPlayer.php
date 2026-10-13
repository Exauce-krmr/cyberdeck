<?php

declare(strict_types=1);

namespace App\Services\Game;

/** Les choix de l'ordinateur : quoi annoncer quand il mène, quelle carte répondre quand le joueur mène. */
final class AiPlayer
{
    /** @var array<string, int[]> valeurs triées de chaque attribut sur toutes les cartes */
    private array $sortedValues = [];

    /**
     * @param array<int, array<string, mixed>> $cards toutes les cartes du jeu
     * @param string[] $attributeKeys
     */
    public function __construct(array $cards, private array $attributeKeys)
    {
        foreach ($attributeKeys as $key) {
            $values = array_column($cards, $key);
            sort($values);
            $this->sortedValues[$key] = $values;
        }
    }

    /**
     * Quand l'ordinateur mène : choisit la carte de sa main et l'attribut à annoncer.
     *
     * @param array<int, array<string, mixed>> $hand
     * @return array{0: int, 1: string} [index dans la main, attribut]
     */
    public function lead(array $hand, string $difficulty): array
    {
        // Facile : au hasard.
        if ($difficulty === 'easy') {
            return [array_rand($hand), $this->attributeKeys[array_rand($this->attributeKeys)]];
        }

        $best = null;
        $pick = [0, $this->attributeKeys[0]];
        foreach ($hand as $index => $card) {
            foreach ($this->attributeKeys as $key) {
                // Normal : la plus haute valeur brute. Difficile : la plus forte par rapport à toutes les cartes du jeu.
                // En cas d'égalité de score, la valeur brute départage.
                $score = $difficulty === 'normal' ? $card[$key] : $this->percentile($key, $card[$key]);
                if ($best === null || [$score, $card[$key]] > $best) {
                    $best = [$score, $card[$key]];
                    $pick = [$index, $key];
                }
            }
        }

        return $pick;
    }

    /**
     * Quand le joueur mène : choisit dans sa main la carte qui répond à l'attribut annoncé.
     *
     * @param array<int, array<string, mixed>> $hand
     */
    public function answer(array $hand, string $attribute, string $difficulty): int
    {
        if ($difficulty === 'easy') {
            return array_rand($hand);
        }
        $values = array_column($hand, $attribute);

        return array_search(max($values), $values, true);
    }

    /** Part des cartes du jeu dont la valeur est inférieure ou égale (entre 0 et 1). */
    private function percentile(string $key, int $value): float
    {
        $values = $this->sortedValues[$key];
        $notAbove = count(array_filter($values, fn (int $v): bool => $v <= $value));

        return $notAbove / count($values);
    }
}
