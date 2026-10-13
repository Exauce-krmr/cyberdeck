// Moments clés de la partie : compare l'état avant / après une manche et dit s'il faut le montrer au joueur (fonction pure, sans DOM).
const CRITICAL_CARDS = 2; // à ce nombre de cartes ou moins, un camp est en danger de mort

const sign = (value) => Math.sign(value);

/** @returns {{tone: string, title: string, detail: string}|null} tone : 'danger' | 'win' | 'alert' | 'pot' */
export function gameEvent(prev, next) {
    // Fin au nombre de manches : le verdict tombe sur les piles, on le dit en grand.
    if (prev?.status === 'playing' && next && next.status !== 'playing' && next.player_count > 0 && next.ai_count > 0) {
        const piles = `${next.player_count} carte${next.player_count > 1 ? 's' : ''} contre ${next.ai_count}`;
        if (next.status === 'win') return { tone: 'win', title: 'Victoire aux cartes', detail: piles };
        if (next.status === 'lose') return { tone: 'danger', title: 'Défaite aux cartes', detail: piles };
        return { tone: 'alert', title: 'Égalité parfaite', detail: piles };
    }
    // Fin par pile vide (aucune des deux piles n'atteint la limite de manches) : la partie est finie, on le dit aussi.
    if (prev?.status === 'playing' && next && next.status !== 'playing') {
        if (next.status === 'win') return { tone: 'win', title: 'Partie gagnée', detail: "L'ordinateur n'a plus aucune carte." };
        if (next.status === 'lose') return { tone: 'danger', title: 'Partie perdue', detail: "Tu n'as plus aucune carte." };
        return { tone: 'alert', title: 'Égalité parfaite', detail: 'Plus aucune carte de part et d\'autre.' };
    }
    if (!prev || !next || next.status !== 'playing' || prev.round === next.round) return null;

    const left = next.max_rounds - next.round;
    if (left === 1) return { tone: 'alert', title: 'Dernière manche', detail: 'La plus grosse pile gagne.' };
    if (next.player_count <= CRITICAL_CARDS && prev.player_count > CRITICAL_CARDS) {
        return { tone: 'danger', title: 'Danger', detail: `Il te reste ${next.player_count} carte${next.player_count > 1 ? 's' : ''}.` };
    }
    if (next.ai_count <= CRITICAL_CARDS && prev.ai_count > CRITICAL_CARDS) {
        return { tone: 'win', title: 'Le système vacille', detail: `L'ordinateur n'a plus que ${next.ai_count} carte${next.ai_count > 1 ? 's' : ''}.` };
    }

    const before = sign(prev.player_count - prev.ai_count);
    const after = sign(next.player_count - next.ai_count);
    // Un vrai changement de tête : au tout début (égalité) personne ne « reprend » rien.
    if (before !== 0 && after === -before) {
        return after > 0
            ? { tone: 'win', title: 'Tu passes en tête', detail: `${next.player_count} cartes contre ${next.ai_count}.` }
            : { tone: 'danger', title: 'Il reprend l\'avantage', detail: `${next.ai_count} cartes contre ${next.player_count}.` };
    }
    if (left === 3) return { tone: 'alert', title: 'Plus que 3 manches', detail: after >= 0 ? 'Garde ton avance.' : 'Il faut remonter, vite.' };
    if (next.pot >= 3 && next.pot > prev.pot) return { tone: 'pot', title: `Cagnotte : ${next.pot} cartes`, detail: 'Le prochain vainqueur rafle tout.' };
    return null;
}
