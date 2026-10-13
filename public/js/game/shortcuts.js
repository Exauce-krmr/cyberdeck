// Raccourcis clavier : renvoie l'intention ({action, index}) ou null. Pur : ne touche ni au DOM ni à l'état.
import { Phase } from './phase.js';

const TYPING = 'input, textarea, select';        // on ne vole jamais une touche pendant la saisie
const ACTIVATABLE = 'button, a';                 // Entrée active déjà ces éléments : pas de double action

export function shortcutFor(event, phase) {
    if (event.ctrlKey || event.metaKey || event.altKey || event.target.closest?.(TYPING)) return null;

    if (event.key === 'Enter' && phase === Phase.REVEALED && !event.target.closest?.(ACTIVATABLE)) return { action: 'next' };

    const inHand = phase === Phase.CHOOSING || phase === Phase.SCANNING || phase === Phase.ANSWERING;
    if (/^[1-9]$/.test(event.key) && inHand) return { action: 'pick', index: Number(event.key) - 1 };
    if (event.key === 'ArrowLeft' && inHand) return { action: 'focus', delta: -1 };
    if (event.key === 'ArrowRight' && inHand) return { action: 'focus', delta: 1 };

    if (event.key === 'Escape' && phase === Phase.SCANNING) return { action: 'cancelScan' };

    return null;
}
