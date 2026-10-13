<?php

// Succès et titres. Une règle compare une mesure (voir App\Services\Achievements\AchievementMetrics) à un seuil :
//   'min' => la mesure doit être >= au seuil ;  'max' => elle doit être <= au seuil.
// Une mesure absente (null) ne débloque rien : par exemple « gagner en moins de 12 manches » n'a de sens que dans une partie gagnée.
return [
    // Titres que le joueur peut afficher. Trois façons de les obtenir (type) :
    //   'count'    : avoir débloqué au moins `min` succès en tout (le plus haut sert de titre par défaut) ;
    //   'category' : avoir débloqué au moins `min` succès de la catégorie `category` ;
    //   'rank'     : occuper le rang `max` ou mieux au classement (il se perd si on est dépassé).
    'titles' => [
        'recruit'   => ['label' => 'Recrue',      'type' => 'count', 'min' => 0],
        'hacker'    => ['label' => 'Hacker',      'type' => 'count', 'min' => 3],
        'netrunner' => ['label' => 'Netrunner',   'type' => 'count', 'min' => 7],
        'legend'    => ['label' => 'Légende',     'type' => 'count', 'min' => 12],
        'ghost'     => ['label' => 'Fantôme',     'type' => 'count', 'min' => 18],

        'gladiator' => ['label' => 'Gladiateur',  'type' => 'category', 'category' => 'games',      'min' => 4],
        'curator'   => ['label' => 'Archiviste',  'type' => 'category', 'category' => 'collection', 'min' => 3],
        'ace'       => ['label' => 'As',          'type' => 'category', 'category' => 'feats',      'min' => 3],
        'strategist' => ['label' => 'Stratège',   'type' => 'category', 'category' => 'deck',       'min' => 3],

        'top10'     => ['label' => 'Top 10',      'type' => 'rank', 'max' => 10],
        'podium'    => ['label' => 'Podium',      'type' => 'rank', 'max' => 3],
        'champion'  => ['label' => 'Numéro 1',    'type' => 'rank', 'max' => 1],

        'shiny'     => ['label' => 'Shiny',       'type' => 'achievement', 'code' => 'all_shiny'],
    ],

    'categories' => [
        'games'      => 'Parties',
        'collection' => 'Collection',
        'feats'      => 'Exploits',
        'deck'       => 'Capacités et deck',
    ],

    'list' => [
        // ---- Parties
        'first_win'  => ['category' => 'games', 'label' => 'Premier sang',      'description' => 'Gagne ta première partie.',                  'metric' => 'wins',        'min' => 1],
        'ten_wins'   => ['category' => 'games', 'label' => 'Habitué',           'description' => 'Gagne 10 parties.',                          'metric' => 'wins',        'min' => 10],
        'streak_3'   => ['category' => 'games', 'label' => 'En feu',            'description' => 'Gagne 3 parties d\'affilée.',                'metric' => 'win_streak',  'min' => 3],
        'hardest_win' => ['category' => 'games', 'label' => 'Sans pitié',       'description' => 'Bats l\'ordinateur en difficulté maximale.', 'metric' => 'wins_hardest', 'min' => 1],
        'veteran'    => ['category' => 'games', 'label' => 'Vétéran',           'description' => 'Termine 25 parties.',                        'metric' => 'games',       'min' => 25],
        'lose_streak' => ['category' => 'games', 'label' => 'Tête dure',        'description' => 'Perds 3 parties d\'affilée et reviens quand même.', 'metric' => 'lose_streak', 'min' => 3],

        // ---- Collection
        'rare_owned'      => ['category' => 'collection', 'label' => 'Éclat',        'description' => 'Possède une carte rare.',        'metric' => 'rare_owned',      'min' => 1],
        'legendary_owned' => ['category' => 'collection', 'label' => 'Légendaire',   'description' => 'Possède une carte légendaire.',  'metric' => 'legendary_owned', 'min' => 1],
        'cards_18'        => ['category' => 'collection', 'label' => 'Collectionneur', 'description' => 'Possède 18 cartes.',          'metric' => 'cards_owned',     'min' => 18],
        'full_collection' => ['category' => 'collection', 'label' => 'Complétiste',  'description' => 'Possède toutes les cartes.',     'metric' => 'cards_missing',   'max' => 0],
        'all_shiny'       => ['category' => 'collection', 'label' => 'Prismatique',  'description' => 'Débloque le skin shiny de toutes les cartes.', 'metric' => 'shiny_missing', 'max' => 0],

        // ---- Exploits en partie
        'no_ability'  => ['category' => 'feats', 'label' => 'Sans filet',   'description' => 'Gagne sans utiliser aucune capacité.',      'metric' => 'win_abilities_used', 'max' => 0],
        'fast_win'    => ['category' => 'feats', 'label' => 'Éclair',       'description' => 'Gagne en 11 manches ou moins.',             'metric' => 'win_rounds',         'max' => 11],
        'clean_win'   => ['category' => 'feats', 'label' => 'Propre',       'description' => 'Gagne en ne perdant que 2 manches ou moins.', 'metric' => 'win_rounds_lost', 'max' => 2],
        'comeback'    => ['category' => 'feats', 'label' => 'Remontada',    'description' => 'Gagne après avoir été mené de 3 manches.',  'metric' => 'win_comeback_depth', 'min' => 3],

        // ---- Capacités et deck
        'scan_used'   => ['category' => 'deck', 'label' => 'Œil de lynx',   'description' => 'Utilise un Scan.',                                  'metric' => 'scans_used',            'min' => 1],
        'all_abilities' => ['category' => 'deck', 'label' => 'Couteau suisse', 'description' => 'Utilise les trois capacités dans une même partie.', 'metric' => 'abilities_used',   'min' => 3],
        'commons_win' => ['category' => 'deck', 'label' => 'Les petits gagnent', 'description' => 'Gagne avec un deck de cartes communes uniquement.', 'metric' => 'win_deck_above_common', 'max' => 0],
        'legends_deck' => ['category' => 'deck', 'label' => 'Équipe de rêve', 'description' => 'Gagne avec 3 cartes légendaires ou plus dans ton deck.', 'metric' => 'win_deck_legendaries', 'min' => 3],
    ],
];
