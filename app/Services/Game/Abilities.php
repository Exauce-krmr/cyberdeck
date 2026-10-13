<?php

declare(strict_types=1);

namespace App\Services\Game;

use DomainException;
use InvalidArgumentException;

/**
 * Capacités à usage limité :
 *  - scan  : quand tu mènes, révèle pour un attribut la meilleure valeur de la main adverse (celle qui te répondra) ;
 *            quand l'ordinateur mène, révèle la valeur exacte de la carte qu'il a jouée pour l'attribut annoncé
 *  - swap  : la première carte de ta main repart sous ta pile, la suivante entre en main (menant ou répondant)
 *  - boost : ajoute un bonus à l'attribut que tu joueras (le bonus lui-même est appliqué par GameEngine) ; seulement quand tu mènes
 */
final class Abilities
{
    /**
     * @param array<string, int> $uses utilisations par partie, pour chaque capacité
     * @param array<int, array<string, mixed>> $cards cartes indexées par id
     * @param string[] $attributeKeys
     */
    public function __construct(private array $uses, private array $cards, private array $attributeKeys, private int $handSize)
    {
    }

    /** @return array<string, int> */
    public function initial(): array
    {
        return $this->uses;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed> nouvel état
     */
    public function use(array $state, string $name, ?string $attribute): array
    {
        if (!isset($this->uses[$name])) {
            throw new InvalidArgumentException('Capacité inconnue.');
        }
        if ($state['status'] !== 'playing') {
            throw new DomainException('La partie est terminée.');
        }
        if ($name === 'boost' && $state['leader'] !== GameEngine::PLAYER) {
            throw new DomainException('Le Boost se joue quand tu mènes la manche.');
        }
        if (($state['abilities'][$name] ?? 0) < 1) {
            throw new DomainException('Capacité déjà utilisée.');
        }

        $state = match ($name) {
            'scan'  => $this->scan($state, $attribute),
            'swap'  => $this->swap($state),
            'boost' => $this->boost($state),
            default => throw new InvalidArgumentException('Capacité inconnue.'),
        };
        $state['abilities'][$name]--;

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function scan(array $state, ?string $attribute): array
    {
        if ($state['leader'] === GameEngine::AI) {
            // L'ordinateur a déjà joué sa carte : le Scan en donne la valeur pour l'attribut qu'il a annoncé.
            ['index' => $index, 'attribute' => $announced] = $state['announced'];
            $state['scan'][$announced] = $this->cards[$state['ai'][$index]][$announced];

            return $state;
        }
        if (!in_array($attribute, $this->attributeKeys, true)) {
            throw new InvalidArgumentException('Attribut inconnu.');
        }
        $hand = array_slice($state['ai'], 0, $this->handSize);
        $state['scan'][$attribute] = max(array_map(fn (int $id): int => $this->cards[$id][$attribute], $hand));

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function swap(array $state): array
    {
        if (count($state['player']) <= $this->handSize) {
            throw new DomainException('Il te faut une carte de plus dans ta pile.');
        }
        $state['player'][] = array_shift($state['player']);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function boost(array $state): array
    {
        if ($state['boost']) {
            throw new DomainException('Boost déjà armé pour cette manche.');
        }
        $state['boost'] = true;

        return $state;
    }
}
