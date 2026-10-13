// Point d'entrée des effets : réexporte l'API utilisée par le reste du jeu. Le détail vit dans les modules voisins :
//   core.js (socle), basic.js (effets courts), shiny.js (cartes shiny), reveal.js (manche, légendaire), endings.js (fins de partie).
// On anime les emplacements (.card-slot) et jamais la carte : elle a déjà son transform (inclinaison holo).

export { flipIn, hit, bump, countTo } from './basic.js';
export { shine } from './shiny.js';
export { revealRound } from './reveal.js';
export { meltdown, defeat, limitDefeat, limitWin, clearEndEffects, playEndSequence } from './endings.js';
