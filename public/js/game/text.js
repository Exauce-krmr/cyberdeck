// Tout ce que le joueur lit pendant la partie, sans aucune logique de jeu ni accès au DOM.
import { Phase } from './phase.js';

/** Bandeau d'état : qui joue, ou ce qui vient de se passer. `tone` pilote la couleur (data-tone en CSS). */
export function banner(phase, { game, result }) {
    if (phase === Phase.CHOOSING) {
        const boost = game.boost ? ` Boost armé : +${game.boost_bonus} sur l'attribut choisi.` : '';
        return { tone: 'you', title: 'Tu mènes', detail: `Choisis un attribut sur l'une de tes deux cartes.${boost}` };
    }
    if (phase === Phase.SCANNING) {
        return { tone: 'you', title: 'Scan', detail: "Clique l'attribut à révéler chez l'adversaire (Échap pour annuler)." };
    }
    if (phase === Phase.ANSWERING) {
        return { tone: 'foe', title: "L'ordinateur mène", detail: `Il a annoncé ${game.announced_label}.` };
    }
    if (phase === Phase.RESOLVING) return { tone: 'muted', title: '…', detail: 'Révélation des cartes' };
    if (phase === Phase.REVEALED) {
        if (isLimitEnd(game)) return limitBanner(game, result);

        return isKoTie(game, result) ? koTieBanner(game, result) : roundBanner(result);
    }
    return { tone: 'muted', title: '', detail: '' };
}

/** Partie finie au nombre de manches (les deux camps ont encore des cartes) : c'est la pile qui décide, pas la dernière manche. */
export const isLimitEnd = (game) => Boolean(game) && game.status !== 'playing' && game.player_count > 0 && game.ai_count > 0;

/** Égalité sur la toute dernière carte d'un camp : elle part en cagnotte, ce camp n'a plus rien à jouer et la partie s'arrête là. */
export const isKoTie = (game, result) => Boolean(game) && result?.outcome === 'tie' && game.status !== 'playing' && (game.player_count === 0 || game.ai_count === 0);

function koTieBanner(game, result) {
    const lost = `La cagnotte (${result.pot} cartes) est perdue.`;
    if (game.status === 'win') return { tone: 'you', title: 'Système hors ligne', detail: `Égalité sur la dernière carte de l'ordinateur : il n'a plus rien à jouer. Tu gagnes la partie. ${lost}` };
    if (game.status === 'lose') return { tone: 'foe', title: 'Plus aucune carte', detail: `Égalité sur ta dernière carte : tu n'as plus rien à jouer. L'ordinateur gagne la partie. ${lost}` };

    return { tone: 'muted', title: 'Égalité parfaite', detail: `Plus aucune carte de part et d'autre. ${lost}` };
}

function limitBanner(game, result) {
    const piles = `${game.player_count} carte${game.player_count > 1 ? 's' : ''} contre ${game.ai_count}`;
    const last = { player: 'Dernière manche gagnée', ai: 'Dernière manche perdue', tie: 'Dernière manche à égalité' }[result.outcome];
    if (game.status === 'win') return { tone: 'you', title: 'Partie gagnée', detail: `${last}. Mais la plus grosse pile l'emporte : ${piles}.` };
    if (game.status === 'lose') return { tone: 'foe', title: 'Partie perdue', detail: `${last}. La plus grosse pile l'emporte : ${piles}.` };
    return { tone: 'muted', title: 'Égalité parfaite', detail: `${last}. Les piles sont à égalité : ${piles}.` };
}

function roundBanner(result) {
    const chooser = result.chosen_by === 'ai' ? "L'ordinateur" : 'Tu';
    const boost = result.boosted ? ' (Boost compris)' : '';
    const detail = `${chooser} ${result.chosen_by === 'ai' ? 'a choisi' : 'as choisi'} ${result.attribute_label} : ${result.player_value}${boost} contre ${result.ai_value}.`;

    if (result.outcome === 'player') return { tone: 'you', title: 'Manche gagnée', detail };
    if (result.outcome === 'ai') return { tone: 'foe', title: 'Manche perdue', detail };
    return { tone: 'muted', title: 'Égalité', detail: `${detail} Les deux cartes vont dans la cagnotte (${result.pot}).` };
}

/** Étiquette sous une carte, du point de vue d'un camp ('player' ou 'ai'). */
export function verdict(side, result, final = null) {
    if (!result) return { text: '', won: false }; // la vue évalue ce binding même quand l'étiquette est masquée
    if (final) { // partie finie aux cartes : l'étiquette dit qui a gagné la PARTIE, pas la dernière manche
        if (final === 'draw') return { text: 'Égalité', won: false };
        const won = (final === 'win') === (side === 'player');

        return won ? { text: 'Victoire', won: true } : { text: 'Défaite', won: false };
    }
    if (result.outcome === 'tie') return { text: 'Égalité', won: false };
    return result.outcome === side ? { text: 'Gagnée', won: true } : { text: 'Perdue', won: false };
}

// Voix de l'ordinateur : des messages système, jamais de « je ». Plusieurs variantes par situation, choisies d'après le numéro de manche.
const FOE_LINES = {
    thinking: ['Analyse des probabilités en cours', 'Simulation de 14 206 issues', 'Noyau tactique synchronisé', 'Protocole d\'attaque armé', 'Cartographie de ta main : 62 %'],
    answering: ['Faille détectée côté humain', 'Réponse attendue. Latence mesurée', 'Verrouillage de l\'attribut confirmé', 'Fenêtre de riposte : ouverte', 'Calcul de ta marge d\'erreur'],
    scanning: ['Intrusion détectée dans la main', 'Pare-feu sous pression', 'Requête externe interceptée', 'Accès non autorisé journalisé'],
    won: ['Domination confirmée', 'Résultat conforme aux prévisions', 'Marge d\'erreur humaine exploitée', 'Probabilité de victoire en hausse', 'Avantage statistique consolidé'],
    lost: ['Anomalie : prévision invalidée', 'Recalcul des stratégies en cours', 'Dégâts enregistrés. Réparation lancée', 'Écart imprévu dans le modèle', 'Facteur humain sous-estimé'],
    tie: ['Collision de valeurs détectée', 'Conflit de données : égalité', 'Résolution impossible. Cagnotte ouverte', 'Symétrie parfaite. Statistique suspecte'],
    strained: ['Charge processeur : 94 %', 'Température du noyau en hausse', 'Latence anormale détectée'],
    down: ['Surcharge totale. Fusion du noyau', 'Arrêt d\'urgence impossible', 'Mémoire effacée. Extinction'],
    final_win: ['Verdict final : unité adverse vaincue', 'Défaite confirmée. Archivage du match', 'Marge humaine sous-estimée. Clôture'],
    final_lose: ['Verdict final : domination confirmée', 'Match archivé. Résultat conforme', 'Avantage statistique validé'],
    final_draw: ['Verdict final : symétrie parfaite', 'Aucun vainqueur désigné par le modèle'],
    critical: ['Intégrité du système critique', 'Fuite mémoire. Cohérence perdue', 'Défaillance en cascade imminente', 'Alerte : circuits en surchauffe'],
};

/** La réplique de l'ordinateur sous son nom. `stress` ('ok' | 'strained' | 'critical') prend le dessus quand il est en difficulté. */
export function foeLine(phase, result, { round = 0, stress = 'ok', final = null } = {}) {
    let kind = 'thinking';
    if (final) return pick(FOE_LINES[`final_${final}`], round, final);
    if (result) kind = { ai: 'won', player: 'lost', tie: 'tie' }[result.outcome];
    else if (phase === Phase.ANSWERING) kind = 'answering';
    else if (phase === Phase.SCANNING) kind = 'scanning';
    if (stress !== 'ok' && (kind !== 'lost' || stress === 'down')) kind = stress;

    return pick(FOE_LINES[kind], round, kind);
}

const pick = (pool, round, kind) => pool[(round * 7 + kind.length) % pool.length];

/** Ce que la victoire rapporte (voir Boosters::awardForWin) : toujours dit clairement, même quand il n'y a pas de booster. */
export function rewardLine(game) {
    const reward = game?.reward;
    if (!reward) return '';
    if (reward.type === 'booster') return `Booster ${game.difficulty_label} ajouté à ton stock.`;
    if (reward.type === 'finished') return 'Collection terminée : plus aucun booster à gagner.';
    if (reward.type === 'shiny_booster') return 'Booster shiny ajouté à ton stock : tu as toutes les cartes !';
    return 'Tu possèdes déjà toutes les cartes de ce booster.'; // les pièces shiny gagnées s'affichent en dessous (play.blade.php)
}
