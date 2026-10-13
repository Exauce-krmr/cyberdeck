// Ce que la table « ressent » de l'état de la partie : fonctions pures (aucun DOM, aucun this), donc testables seules.
import { Phase } from './phase.js';
import { isLimitEnd } from './text.js';

// Tension de la table : part de cartes en jeu que tu possèdes (cagnotte comprise).
const TENSION = { danger: 0.25, tense: 0.38, winning: 0.65, lastCards: 2 };
// Ordinateur en difficulté : sa part de cartes en jeu (cagnotte comprise), ou son nombre de cartes.
const FOE_STRESS = { strained: 0.33, critical: 0.2, lastCards: 2 };
const ENDGAME_SOON_ROUNDS = 3;

const isPlaying = (game) => Boolean(game) && game.status === 'playing';
const totalInPlay = ({ player_count: player, ai_count: ai, pot }) => Math.max(1, player + ai + pot);

/** Ambiance du plateau (data-mood) : suit ce qui se passe dans la manche. */
export function moodOf(game, result, phase) {
    if (!game || (game.status !== 'playing' && !result)) return 'none';
    if (result) return isLimitEnd(game) ? { win: 'win', lose: 'lose', draw: 'tie' }[game.status] : { player: 'win', ai: 'lose', tie: 'tie' }[result.outcome];

    return phase === Phase.ANSWERING ? 'foe' : 'you';
}

/** 'danger' | 'tense' | 'calm' | 'winning' | 'none' : pilote le voile rouge qui bat et la jauge d'alarme. */
export function tensionOf(game) {
    if (!isPlaying(game)) return 'none';
    const share = game.player_count / totalInPlay(game);
    if (share <= TENSION.danger || game.player_count <= TENSION.lastCards) return 'danger';
    if (share <= TENSION.tense) return 'tense';

    return share >= TENSION.winning ? 'winning' : 'calm';
}

/** 'ok' | 'strained' | 'critical' | 'down' : l'ordinateur « stresse » quand il perd ses cartes. */
export function foeStressOf(game) {
    if (!game) return 'ok';
    if (game.ai_count === 0) return 'down';
    if (game.status !== 'playing') return 'ok';
    const share = game.ai_count / totalInPlay(game);
    if (share <= FOE_STRESS.critical || game.ai_count <= FOE_STRESS.lastCards) return 'critical';

    return share <= FOE_STRESS.strained ? 'strained' : 'ok';
}

/** Fin de partie qui approche : 'soon' (3 manches ou moins) | 'last' (dernière) | 'none'. */
export function endgameOf(game) {
    if (!isPlaying(game)) return 'none';
    const left = game.max_rounds - game.round;
    if (left <= 1) return 'last';

    return left <= ENDGAME_SOON_ROUNDS ? 'soon' : 'none';
}

/** Part (en %, une décimale) d'un compteur ('player_count' | 'ai_count' | 'pot') dans le total des cartes en jeu. */
export function shareOf(game, counter, over) {
    const pot = over ? 0 : game.pot; // partie finie : la cagnotte est perdue, elle ne compte plus
    const count = counter === 'pot' ? pot : game[counter];

    return ((count / (game.player_count + game.ai_count + pot)) * 100).toFixed(1);
}
