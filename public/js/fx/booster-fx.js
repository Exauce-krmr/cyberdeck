// Effets de lumière de l'ouverture des boosters : particules, ondes de choc, poussière dorée. Rien d'essentiel : sans Motion
// (ou avec « réduire les animations ») l'ouverture fonctionne, simplement sans éclats.
// Les couleurs viennent de boosters.css ([data-tone] → --tone-rgb) : une seule source.

import { animate, canAnimate, centerOf, rand } from './core.js';
import { SHINY_COLORS as RAINBOW } from './shiny.js'; // nacre, comme le cadre des cartes shiny

export { canAnimate }; // les modules du booster l'importent d'ici
const cache = new Map();

/** « r g b » d'un ton (easy, normal, hard, common, rare, epic, legendary, shiny), lu dans le CSS. */
export function toneRgb(tone) {
    if (!cache.has(tone)) {
        const probe = document.createElement('i');
        probe.dataset.tone = tone;
        probe.hidden = true;
        document.body.append(probe);
        cache.set(tone, getComputedStyle(probe).getPropertyValue('--tone-rgb').trim() || '255 255 255');
        probe.remove();
    }

    return cache.get(tone);
}

const colorFor = (tone, index) => (tone === 'shiny' ? RAINBOW[index % RAINBOW.length] : toneRgb(tone));

function spawn(className, point, rgb, css = '', lifeMs = 2000) {
    const node = document.createElement('i');
    node.className = className;
    node.setAttribute('aria-hidden', 'true');
    node.style.cssText = `left:${point.x}px;top:${point.y}px;--c:${rgb};${css}`;
    document.body.append(node);
    setTimeout(() => node.remove(), lifeMs);

    return node;
}

export { centerOf }; // même mesure que les fins de partie (core.js)

/** Particules qui sont aspirées vers le paquet pendant qu'il se charge. */
export function inflow(point, tone, count = 3) {
    if (!canAnimate) return;
    for (let i = 0; i < count; i++) {
        const angle = Math.random() * Math.PI * 2;
        const distance = rand(180, 420);
        const start = { x: point.x + Math.cos(angle) * distance, y: point.y + Math.sin(angle) * distance };
        const dot = spawn('fx-dot', start, colorFor(tone, i), '', 1000);
        animate(dot, { x: point.x - start.x, y: point.y - start.y, opacity: [0, 1, 0], scale: [1.4, 0.4] }, { duration: rand(0.45, 0.85), ease: 'easeIn' });
    }
}

/** Anneau lumineux qui s'élargit. */
export function shockwave(point, tone, size = 8, delay = 0) {
    if (!canAnimate) return;
    const ring = spawn('fx-ring', point, colorFor(tone, 0), `width:${size}rem;height:${size}rem;`, 1500);
    animate(ring, { scale: [0.2, 4], opacity: [0.95, 0] }, { duration: 0.9, delay, ease: 'easeOut' });
}

/** Étincelles allongées projetées dans toutes les directions. */
export function sparks(point, tone, count = 20, power = 1) {
    if (!canAnimate) return;
    for (let i = 0; i < count; i++) {
        const angle = Math.random() * Math.PI * 2;
        const distance = rand(120, 420) * power;
        const spark = spawn('fx-spark', point, colorFor(tone, i), `height:${rand(0.8, 2.4)}rem;rotate:${angle + Math.PI / 2}rad;`, 1400);
        animate(spark, { x: Math.cos(angle) * distance, y: Math.sin(angle) * distance, opacity: [1, 1, 0], scaleY: [1, 0.2] }, { duration: rand(0.45, 0.95), ease: 'easeOut' });
    }
}

/** Pluie de paillettes scintillantes qui tombent sur tout l'écran (légendaire, shiny). */
export function dust(tone, count = 60, spreadMs = 2500) {
    if (!canAnimate) return;
    for (let i = 0; i < count; i++) {
        const point = { x: rand(0, innerWidth), y: -20 };
        const speck = spawn('fx-dust', point, colorFor(tone, i), `scale:${rand(0.5, 1.4)};`, spreadMs + 5200);
        animate(speck, { y: innerHeight + 40, x: [0, rand(-60, 60), rand(-60, 60)], rotate: rand(-360, 360), opacity: [0, 1, 0.25, 1, 0.3, 1, 0] }, { duration: rand(2.4, 4.6), delay: rand(0, spreadMs / 1000), ease: 'linear' });
    }
}

/** Éclair de couleur sur tout l'écran. */
export function flash(tone) {
    if (!canAnimate) return;
    const node = document.createElement('div');
    node.className = 'opening-flash';
    node.dataset.tone = tone;
    node.setAttribute('aria-hidden', 'true');
    document.body.append(node);
    animate(node, { opacity: [0, 0.8, 0] }, { duration: 1.1, times: [0, 0.22, 1], ease: 'easeOut' });
    setTimeout(() => node.remove(), 1200);
}

export function shake(element, power = 1) {
    if (!canAnimate || !element) return;
    const p = 10 * power;
    animate(element, { x: [0, -p, p, -p * 0.7, p * 0.7, -p * 0.3, 0], y: [0, p * 0.4, -p * 0.4, p * 0.3, 0, 0, 0] }, { duration: 0.5 });
}

/** L'explosion de lumière quand une carte se retourne : plus elle est rare, plus c'est grand. */
export function burst(rarity, point, stage) {
    const tone = rarity;
    flash(tone);
    if (rarity === 'common') {
        shockwave(point, tone, 6);
        sparks(point, tone, 10, 0.7);
    } else if (rarity === 'rare') {
        shockwave(point, tone, 7);
        sparks(point, tone, 22);
    } else if (rarity === 'epic') {
        shockwave(point, tone, 8);
        shockwave(point, tone, 8, 0.15);
        sparks(point, tone, 38, 1.2);
        shake(stage, 0.8);
    } else {
        // légendaire ou shiny : trois ondes, gerbe d'étincelles, secousse et pluie de paillettes
        [0, 0.12, 0.26].forEach((delay) => shockwave(point, tone, 9, delay));
        sparks(point, tone, 70, 1.5);
        shake(stage, 1.4);
        dust(tone, 70, 2600);
    }
}
