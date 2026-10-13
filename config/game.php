<?php

// Tous les réglages du jeu sont ici : rien n'est écrit en dur dans le code.
return [
    // clé = colonne de la table cards, valeur = libellé affiché
    'attributes' => [
        'power'        => 'Puissance',
        'speed'        => 'Vitesse',
        'intelligence' => 'Intelligence',
        'hacking'      => 'Piratage',
        'threat'       => 'Menace',
    ],

    'deck_size'     => 10, // cartes de chaque joueur au départ (le deck que tu composes)
    'hand_size'     => 2,  // cartes en main : les premières de la pile ; tu en choisis une à chaque manche
    'max_rounds'    => 20, // au-delà, celui qui a le plus de cartes gagne
    'boost_bonus'   => 15, // points ajoutés à l'attribut joué quand le Boost est armé
    // Nombre maximum de cartes d'une rareté dans un deck (une rareté absente d'ici n'a pas de limite) : évite les decks trop forts.
    'deck_limits'   => ['legendary' => 3, 'epic' => 4],
    'starter_cards' => 12, // cartes communes offertes à l'inscription (doit être >= deck_size)

    // Boosters offerts à l'inscription, par difficulté (comme ceux gagnés en victoire) : de quoi se lancer sans attendre de gagner.
    'welcome_boosters' => ['easy' => 2, 'normal' => 2, 'hard' => 2],

    // weight : chance de tirage de CHAQUE carte de cette rareté (plus c'est bas, plus la carte est rare).
    // L'ordre compte : de la plus commune à la plus haute.
    'rarities' => [
        'common'    => ['label' => 'Commun',     'weight' => 20, 'holo' => false],
        'rare'      => ['label' => 'Rare',       'weight' => 12, 'holo' => true],
        'epic'      => ['label' => 'Épique',     'weight' => 8,  'holo' => true],
        'legendary' => ['label' => 'Légendaire', 'weight' => 3,  'holo' => true],
    ],

    // points : niveau de la difficulté, affiché en barres sur l'écran de préparation (le classement ne compte plus de points)
    'difficulties' => [
        'easy'   => ['label' => 'Facile',    'points' => 1, 'description' => 'Joue et répond au hasard.'],
        'normal' => ['label' => 'Normal',    'points' => 2, 'description' => 'Annonce sa plus haute valeur, répond avec sa meilleure carte.'],
        'hard'   => ['label' => 'Difficile', 'points' => 3, 'description' => 'Annonce l\'attribut où sa carte écrase le reste du jeu.'],
    ],

    // Boosters gagnés en victoire. cards : cartes tirées (jamais un doublon) ; rarities : raretés possibles (null = toutes) ;
    // max : nombre maximum de cartes d'une rareté dans un même booster (levé si plus rien d'autre n'est à tirer) ;
    // boost : multiplicateur de la chance de tirage d'une rareté pour ce booster (1 = normal, < 1 = plus rare, > 1 = plus fréquente).
    // Quand le joueur possède toutes les cartes qu'un booster peut donner, il n'en reçoit plus (voir app/Services/Boosters/Boosters.php).
    'boosters' => [
        'easy'   => ['cards' => 2, 'rarities' => ['common', 'rare', 'epic'], 'boost' => ['epic' => 0.5]],                               // jamais de légendaire, épique assez rare
        'normal' => ['cards' => 3, 'rarities' => null, 'boost' => []],                                                                    // toutes les raretés, légendaire comprise
        'hard'   => ['cards' => 5, 'rarities' => null, 'boost' => ['rare' => 2.0, 'epic' => 2.5, 'legendary' => 3.0], 'max' => ['common' => 3]], // au plus 3 communes, chances boostées sur les cartes rares et plus
    ],

    // Shiny : skin à équiper, débloqué par les boosters shiny (donnés en Normal / Difficile une fois les 60 cartes obtenues).
    'shiny' => [
        'chance'            => ['easy' => 25, 'normal' => 50, 'hard' => 70], // % qu'un booster shiny contienne un shiny, selon la difficulté d'origine (sans entrée : pas de booster shiny, des points à la place)
        // Quelle carte reçoit le skin : multiplicateur de chance par rareté (1 = tirage normal, où les communes dominent). Plus la difficulté est haute, plus l'épique et la légendaire sont favorisées.
        'rarity_boost'      => ['easy' => ['epic' => 1.5, 'legendary' => 2.0], 'normal' => ['epic' => 2.5, 'legendary' => 4.0], 'hard' => ['epic' => 4.0, 'legendary' => 8.0]],
        'cost'              => 5, // points shiny pour acheter un booster shiny (possible seulement collection complète)
        'purchase_difficulty' => 'normal', // chance du booster acheté
        'miss_points'       => 2, // points si le booster shiny ne contient rien
        'obsolete_points'   => 1, // points quand un booster en stock devient inutile (tout est déjà obtenu)
        'leftover_points'   => 1, // points par carte que le booster n'a pas pu donner (plus rien à tirer)
    ],

    // uses : nombre d'utilisations par partie. Scan et Swap se jouent en attaque comme en défense ; le Boost seulement quand tu mènes.
    'abilities' => [
        'scan'  => ['label' => 'Scan',  'uses' => 1, 'description' => "Révèle une valeur de la main adverse."],
        'swap'  => ['label' => 'Swap',  'uses' => 2, 'description' => "Ta première carte repart sous ta pile, la suivante entre en main."],
        'boost' => ['label' => 'Boost', 'uses' => 1, 'description' => "Ajoute +15 à l'attribut que tu joues (en attaque uniquement)."],
    ],
];
