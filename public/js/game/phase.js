// La partie est toujours dans UNE phase, calculée à partir des données : on ne stocke pas de drapeaux
// qui pourraient se contredire. Chaque action n'est permise que dans certaines phases : un double clic,
// une touche pressée au mauvais moment ou une réponse tardive deviennent des no-ops au lieu de bugs.

export const Phase = Object.freeze({
    LOADING: 'loading',      // on demande au serveur s'il y a une partie en cours
    IDLE: 'idle',            // pas de partie : choix de la difficulté
    CHOOSING: 'choosing',    // tu mènes : choisis une carte de ta main et un attribut
    ANSWERING: 'answering',  // l'ordinateur a annoncé un attribut : choisis la carte de ta main qui répond
    SCANNING: 'scanning',    // Scan armé : le prochain attribut cliqué est révélé chez l'adversaire au lieu d'être joué
    RESOLVING: 'resolving',  // requête en vol
    REVEALED: 'revealed',    // les deux cartes sont montrées, en attente de « Manche suivante »
    FINISHED: 'finished',    // partie terminée, résultat final affiché
});

const ALLOWED = {
    start: [Phase.IDLE, Phase.FINISHED],
    pick: [Phase.CHOOSING, Phase.ANSWERING],
    scan: [Phase.SCANNING],
    ability: [Phase.CHOOSING, Phase.SCANNING, Phase.ANSWERING], // en défense : Swap et Scan seulement (voir canUseAbility)
    next: [Phase.REVEALED],
    abandon: [Phase.CHOOSING, Phase.ANSWERING, Phase.SCANNING],
};

/** @param {{loaded: boolean, game: object|null, result: object|null, busy: boolean, scanArmed: boolean}} state */
export function phaseOf({ loaded, game, result, busy, scanArmed }) {
    if (!loaded) return Phase.LOADING;
    if (!game) return Phase.IDLE;
    if (result) return Phase.REVEALED;
    if (game.status !== 'playing') return Phase.FINISHED;
    if (busy) return Phase.RESOLVING;
    if (game.leader !== 'player') return Phase.ANSWERING;
    return scanArmed ? Phase.SCANNING : Phase.CHOOSING;
}

export const isAllowed = (phase, action) => ALLOWED[action].includes(phase);
