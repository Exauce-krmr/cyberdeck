// Effets simples et courts : retournement, coup de zoom, glitch express, compteur, éclair de manche.
import { animate, canAnimate, prefersReducedMotion, screenFlash } from './core.js';

const slotsIn = (element) => {
    if (!element) return [];
    return element.matches('.card-slot') ? [element] : [...element.querySelectorAll('.card-slot')];
};

/** Retourne les cartes d'un conteneur, l'une après l'autre. */
export function flipIn(element) {
    if (!canAnimate) return;
    slotsIn(element).forEach((slot, i) => {
        animate(slot, { rotateY: [90, 0], opacity: [0, 1] }, { duration: 0.45, delay: i * 0.12, ease: 'easeOut' });
    });
}

// Mêmes images clés que @keyframes glitch-hit (play.css). Jouées par l'API Web Animations : elles se SUPERPOSENT aux animations CSS de
// l'élément (apparition, lueur…) au lieu de les remplacer, donc rien ne se relance une fois le glitch fini.
const GLITCH_STEP = 'steps(1, end)';
const GLITCH_KEYFRAMES = [
    { offset: 0, transform: 'none', textShadow: 'none', filter: 'none' },
    { offset: 0.15, transform: 'translate(-4px, 1px) skewX(-8deg)', textShadow: '3px 0 rgb(255 40 90 / .9), -3px 0 rgb(40 230 255 / .9)', filter: 'hue-rotate(40deg) brightness(1.4)' },
    { offset: 0.3, transform: 'translate(4px, -2px) skewX(6deg)', textShadow: '-3px 0 rgb(255 40 90 / .9), 3px 0 rgb(40 230 255 / .9)' },
    { offset: 0.48, transform: 'translate(-2px, 0)', filter: 'none' },
    { offset: 0.64, transform: 'translate(3px, 1px) skewX(-4deg)', textShadow: '2px 0 rgb(255 40 90 / .8), -2px 0 rgb(40 230 255 / .8)', filter: 'brightness(1.3)' },
    { offset: 0.82, transform: 'translate(-1px, 0)' },
    { offset: 1, transform: 'none', textShadow: 'none', filter: 'none' },
].map((frame) => ({ ...frame, easing: GLITCH_STEP }));
const GLITCH_MS = 450;

/** Glitch express (sans librairie : marche aussi sur le texte que le jeu réécrit sans cesse). */
export function hit(element) {
    if (!element || prefersReducedMotion) return;
    element.getAnimations().filter((running) => running.id === 'glitch-hit').forEach((running) => running.cancel()); // un seul glitch à la fois
    element.animate(GLITCH_KEYFRAMES, { duration: GLITCH_MS, id: 'glitch-hit' });
}

/** Petit coup de zoom sur un nombre qui change. */
export function bump(element) {
    if (!canAnimate || !element) return;
    animate(element, { scale: [1, 1.45, 1] }, { duration: 0.4, ease: 'easeOut' });
}

/** Éclair plein écran à la révélation : bleu si tu gagnes, rouge si tu perds. Rien en cas d'égalité. */
export function roundFlash(outcome) {
    if (!['player', 'ai', 'tie'].includes(outcome)) return;
    screenFlash('impact-flash', { dataset: { outcome }, duration: 0.5, delay: 0.5, lifeMs: 1100 });
}

/** Fait défiler un compteur de `from` à `to` ; `onUpdate` reçoit chaque valeur entière. */
export function countTo(from, to, onUpdate) {
    if (!canAnimate || from === to) return onUpdate(to);
    animate(from, to, { duration: 0.6, ease: 'easeOut', onUpdate: (value) => onUpdate(Math.round(value)) });
}
