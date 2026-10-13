<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Core\Config;
use App\Core\View;
use App\Services\Game\GameEngine;

/**
 * Transforme l'état d'une partie en réponse JSON pour le navigateur : libellés, cartes rendues côté serveur avec le même gabarit
 * que partout ailleurs (partials/card), piles. Aucune règle de jeu ici.
 */
final class GamePresenter
{
    /** @param int[] $shinies ids des cartes dont le joueur a équipé le skin shiny : il n'est affiché que sur SES cartes */
    public function __construct(private View $view, private array $shinies)
    {
    }

    /**
     * @param array<string, mixed> $game
     * @return array<string, mixed>
     */
    public function game(array $game): array
    {
        $yourTurn = $game['status'] === 'playing' && $game['leader'] === GameEngine::PLAYER;
        $handOptions = ['selectable' => $yourTurn, 'foe' => $game['scan'], 'highlight' => $game['announced']];

        $json = $game + [
            'announced_label'  => $game['announced'] === null ? null : $this->attributeLabel($game['announced']),
            'difficulty_label' => Config::get("game.difficulties.{$game['difficulty']}.label"),
            'hand_html'        => array_map(fn (array $card): string => $this->card($card, $handOptions + ['shiny' => $this->isShiny($card)]), $game['player_hand']),
            'boost_bonus'      => Config::get('game.boost_bonus'),
            'my_pile_html'     => $this->view->render('partials.my-pile', ['pile' => $game['player_pile']]),
            'pile_html'        => $this->view->render('partials.pile', ['known' => $game['ai_known'], 'unknown' => $game['ai_unknown']]),
        ];
        unset($json['player_hand'], $json['player_pile'], $json['ai_known']);
        if ($json['summary'] !== null) {
            $json['summary']['rounds'] = array_map(fn (array $round): array => $round + ['attribute_label' => $this->attributeLabel($round['attribute'])], $json['summary']['rounds']);
            if ($json['summary']['biggest'] !== null) {
                $json['summary']['biggest']['attribute_label'] = $this->attributeLabel($json['summary']['biggest']['attribute']);
            }
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function result(array $result): array
    {
        $attribute = $result['attribute'];

        return [
            'attribute'       => $attribute,
            'attribute_label' => $this->attributeLabel($attribute),
            'outcome'         => $result['outcome'],
            'chosen_by'       => $result['chosen_by'],
            'player_value'    => $result['player_value'],
            'ai_value'        => $result['ai_value'],
            'boosted'         => $result['boosted'],
            'pot'             => $result['pot'],
            'player_html'     => $this->card($result['player_card'], [
                'shiny'     => $this->isShiny($result['player_card']),
                'highlight' => $attribute,
                'winner'    => $result['outcome'] === GameEngine::PLAYER,
            ]),
            'ai_html'         => $this->card($result['ai_card'], [
                'highlight' => $attribute,
                'winner'    => $result['outcome'] === GameEngine::AI,
            ]),
        ];
    }

    private function attributeLabel(string $attribute): string
    {
        return Config::get("game.attributes.{$attribute}");
    }

    /** @param array<string, mixed> $card */
    private function isShiny(array $card): bool
    {
        return in_array($card['id'], $this->shinies, true);
    }

    /**
     * @param array<string, mixed> $card
     * @param array<string, mixed> $options
     */
    private function card(array $card, array $options = []): string
    {
        return $this->view->render('partials.card', ['card' => $card] + $options);
    }
}
