// Révélation d'une manche, et annonce plein écran d'une légendaire.
import { animate, canAnimate } from './core.js';
import { hit, roundFlash } from './basic.js';
import { shine, shinyRain } from './shiny.js';

const LEGEND_DELAY_SECONDS = 0.4;
const LEGEND_SCREEN_SECONDS = 2.4; // assez long pour lire le nom ; un clic le ferme avant
const LEGEND_SCREEN_SHINY_SECONDS = 3.2; // la shiny est plus rare : on la laisse un peu plus longtemps

/** Plein écran : bande dorée et nom de la légendaire qui claque, puis il reste le temps de le lire. Un clic le coupe. Une légendaire SHINY a sa propre version (arc-en-ciel, plus longue, pluie d'étoiles). */
function legendaryScreen(slot, delay) {
    const name = slot.querySelector('.card-name')?.textContent.trim() ?? '';
    const shiny = Boolean(slot.querySelector('.is-shiny'));
    const seconds = shiny ? LEGEND_SCREEN_SHINY_SECONDS : LEGEND_SCREEN_SECONDS;
    const screen = document.createElement('div');
    screen.className = 'legend-screen';
    if (shiny) screen.dataset.shiny = 'true';
    screen.setAttribute('aria-hidden', 'true');
    const lines = shiny ? '<i class="legend-streak legend-streak-thin legend-streak-top"></i><i class="legend-streak legend-streak-thin legend-streak-bottom"></i>' : '';
    screen.innerHTML = `${lines}<i class="legend-streak"></i><p class="legend-title"><small>${shiny ? '✦ Légendaire ✦' : 'Légendaire'}</small><strong></strong></p>`;
    screen.querySelector('strong').textContent = name;
    // La promesse se résout quand l'annonce disparaît (fin du délai ou clic) : les fins de partie s'enchaînent APRÈS elle, jamais par-dessus.
    let finish = () => {};
    const gone = new Promise((resolve) => { finish = resolve; });
    const close = () => { screen.remove(); finish(); };
    screen.addEventListener('click', close);
    document.body.append(screen);
    animate(screen, { opacity: [0, 1, 1, 0] }, { duration: seconds, delay, times: [0, 0.08, 0.8, 1] });
    animate([...screen.querySelectorAll('.legend-streak')], { scaleX: [0, 1] }, { duration: 0.3, delay, ease: 'easeOut' });
    animate(screen.querySelector('.legend-title'), { scale: [1.8, 1], opacity: [0, 1] }, { duration: 0.45, delay: delay + 0.1, ease: 'easeOut' });
    if (shiny) shinyRain(screen, delay);
    setTimeout(close, (delay + seconds + 0.1) * 1000);

    return gone;
}

/**
 * Révélation d'une manche : la carte adverse se retourne, puis la gagnante pulse ; les légendaires font de l'effet.
 * @returns {Promise<void>} résolue quand l'écran de la légendaire (s'il y en a un) a disparu : c'est le feu vert pour une séquence de fin de partie
 */
export function revealRound(arena, outcome) {
    if (!canAnimate || !arena) return Promise.resolve();
    // L'annonce plein écran ne concerne qu'une légendaire qui GAGNE la manche (une seule annonce par manche).
    const legendary = [...arena.querySelectorAll('.card-slot')].filter((slot) => slot.querySelector('.rarity-legendary'));
    const star = legendary.find((slot) => slot.querySelector('.is-winner'));
    const announced = star ? legendaryScreen(star, LEGEND_DELAY_SECONDS) : Promise.resolve();
    const vs = arena.querySelector('.arena-vs');
    if (vs) {
        animate(vs, { scale: [2.4, 1], rotate: [-25, 0] }, { duration: 0.4, delay: 0.5, ease: 'easeOut' });
        setTimeout(() => hit(vs.querySelector('span')), 550);
    }
    if (star || outcome === 'ai') animate(arena, { x: [0, -8, 8, -6, 6, -3, 0] }, { duration: 0.4, delay: 0.5 }); // une manche perdue secoue la table
    roundFlash(outcome);
    const foeSlot = arena.querySelector('.arena-ai .card-slot');
    if (foeSlot) animate(foeSlot, { rotateY: [90, 0], opacity: [0, 1] }, { duration: 0.5, ease: 'easeOut' });

    arena.querySelectorAll('.card-wrap.is-shiny').forEach((wrap) => shine(wrap, wrap.classList.contains('is-winner')));

    const winnerSlot = arena.querySelector('.is-winner')?.closest('.card-slot');
    if (winnerSlot) animate(winnerSlot, { scale: [1, 1.05, 1] }, { duration: 0.5, delay: 0.55 });
    return announced;
}
