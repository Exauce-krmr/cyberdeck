# Deck adverse calibré sur la force du joueur (idée en attente)

Non utilisé par le jeu : ce dossier est hors de `app/`. À reprendre quand il y aura plus de cartes.

Pour l'activer :
1. Ajouter dans `config/game.php` un réglage `ai_deck_ratio` (ex. `easy` 0.85, `normal` 1.0, `hard` 1.15).
2. Déplacer `BudgetAiDeck.php` dans `app/Services/` (namespace `App\Services`).
3. Dans `app/bootstrap.php`, remplacer `AiDeck` par `BudgetAiDeck`, et dans `GameService::start()` appeler `build($deckIds, $difficulty, $this->handSize)`.
4. Mettre à jour le texte de la page de jeu (« L'ordinateur reçoit les mêmes raretés que toi ») et ajouter un test : la force de l'ordinateur reste proche de la cible.

Limite : avec peu de cartes, la cible n'est atteinte qu'approximativement (24 cartes aujourd'hui).
