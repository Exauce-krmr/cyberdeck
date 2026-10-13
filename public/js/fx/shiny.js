// Effets des cartes shiny : étoiles de couleur qui s'échappent, éclat qui accélère, rebond.
import { animate, canAnimate, rand } from './core.js';

export const SHINY_COLORS = ['238 180 214', '242 217 143', '159 230 207', '159 192 245', '200 166 240']; // nacre : mêmes teintes douces que le cadre (css/shiny.css)

const SHINY_RAIN = 46;
const SHINE_MOTES_WINNER = 14;
const SHINE_MOTES_LOSER = 6;
const SHINE_CLASS_MS = 1400;

/** Une étoile de couleur posée dans `parent` ; `css` règle sa position et sa taille. */
function mote(parent, index, css) {
    const node = document.createElement('i');
    node.className = 'shiny-mote';
    node.setAttribute('aria-hidden', 'true');
    node.style.cssText = `${css};--c:rgb(${SHINY_COLORS[index % SHINY_COLORS.length]})`;
    parent.append(node);

    return node;
}

/** Étoiles de couleur qui montent et tombent dans l'annonce d'une légendaire shiny (elles vivent dans l'écran d'annonce : au-dessus de lui, pas dessous). */
export function shinyRain(screen, delay) {
    for (let i = 0; i < SHINY_RAIN; i++) {
        const size = rand(0.6, 1.7);
        const star = mote(screen, i, `left:${rand(0, 100)}%;top:${rand(20, 90)}%;width:${size}rem;height:${size}rem`);
        animate(star, { y: [0, -rand(60, 220)], x: [0, rand(-45, 45)], opacity: [0, 1, 0], scale: [0.2, 1.1, 0.3], rotate: [0, 120] }, { duration: rand(1.2, 2.4), delay: delay + rand(0.2, 1.6), ease: 'easeOut' });
    }
}

/**
 * Une carte shiny qui se dévoile : l'éclat la traverse plus vite, elle fait un petit rebond et des étoiles de couleur s'en échappent.
 * Plus généreux quand c'est elle qui gagne la manche. N'efface rien : tout part tout seul au bout de ~1,6 s.
 */
export function shine(wrap, winner = false) {
    const slot = wrap?.closest('.card-slot');
    if (!canAnimate || !slot) return;
    wrap.classList.remove('shiny-shine');
    void wrap.offsetWidth;
    wrap.classList.add('shiny-shine');
    setTimeout(() => wrap.classList.remove('shiny-shine'), SHINE_CLASS_MS);

    const count = winner ? SHINE_MOTES_WINNER : SHINE_MOTES_LOSER;
    for (let i = 0; i < count; i++) {
        const star = mote(slot, i, `left:${rand(10, 90)}%;top:${rand(25, 85)}%`);
        animate(star, { y: [0, -rand(30, 90)], x: [0, rand(-25, 25)], opacity: [0, 1, 0], scale: [0.3, 1.1, 0.2], rotate: [0, 90] }, { duration: rand(0.9, 1.5), delay: rand(0.5, 1), ease: 'easeOut' });
        setTimeout(() => star.remove(), 2400);
    }
    animate(wrap, { scale: winner ? [1, 1.07, 1] : [1, 1.03, 1] }, { duration: 0.6, delay: 0.45, ease: 'easeOut' });
}
