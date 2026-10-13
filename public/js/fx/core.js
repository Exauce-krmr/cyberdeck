// Socle commun des effets : Motion (si présent), « réduire les animations », et quelques utilitaires partagés.
// Rien d'essentiel ne dépend des effets : sans Motion ou avec « réduire les animations », le jeu fonctionne, simplement sans mouvement.

export const animate = window.Motion?.animate;
export const prefersReducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
export const canAnimate = Boolean(animate) && !prefersReducedMotion;

export const rand = (min, max) => min + Math.random() * (max - min);

export const centerOf = (element) => {
    const box = element.getBoundingClientRect();
    return { x: box.left + box.width / 2, y: box.top + box.height / 2, w: box.width, h: box.height };
};

/** Ajoute un élément décoratif plein écran (aria-hidden) qui se retire tout seul. */
export function overlay(className, html, lifeMs) {
    const node = document.createElement('div');
    node.className = className;
    node.setAttribute('aria-hidden', 'true');
    if (html) node.innerHTML = html;
    document.body.append(node);
    setTimeout(() => node.remove(), lifeMs);
    return node;
}

/** Éclair plein écran : un calque qui s'allume puis s'éteint (`opacity` en trois temps), retiré ensuite. */
export function screenFlash(className, { dataset = {}, duration, delay = 0, peak = 0.2, lifeMs }) {
    const flash = overlay(className, '', lifeMs);
    Object.assign(flash.dataset, dataset);
    animate(flash, { opacity: [0, 1, 0] }, { duration, delay, times: [0, peak, 1] });

    return flash;
}
