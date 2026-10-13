// Inspection d'une carte de la collection : fond assombri, carte agrandie qu'on incline au doigt ou à la souris.
// L'inclinaison et le reflet sont ceux de holo.js (variables CSS --mx, --my, --rx, --ry) ; ici on ajoute
// l'ouverture/fermeture, les étoiles des légendaires et un balancement tant qu'on ne touche pas la carte.

const TILT_DEGREES = 26;
const SPARKLES = { legendary: 34 }; // étoiles scintillantes des légendaires
const SHINY_SPARKLES = 26;          // et des cartes shiny, quelle que soit leur rareté
const SWAY_SPEED = 0.0009;
const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function rarityOf(wrap) {
    return ['legendary', 'epic', 'rare', 'common'].find((name) => wrap.classList.contains(`rarity-${name}`)) ?? 'common';
}

export function addSparkles(body, count) {
    const layer = document.createElement('div');
    layer.className = 'sparkles';
    layer.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < count; i++) {
        const star = document.createElement('i');
        star.style.setProperty('--x', `${Math.random() * 100}%`);
        star.style.setProperty('--y', `${Math.random() * 100}%`);
        star.style.setProperty('--s', `${0.35 + Math.random() * 0.9}rem`);
        star.style.setProperty('--d', `${Math.random() * 3}s`);
        star.style.setProperty('--t', `${1.6 + Math.random() * 2}s`);
        star.style.setProperty('--depth', `${(Math.random() * 2 - 1).toFixed(2)}`);
        layer.append(star);
    }
    body.append(layer);
}

function open(slot) {
    const source = slot.querySelector('.card-wrap');
    if (!source) return;
    const opener = document.activeElement;

    const overlay = document.createElement('div');
    overlay.className = 'inspect';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Inspection de carte');

    const stage = document.createElement('div');
    stage.className = 'inspect-stage card-slot';
    const wrap = source.cloneNode(true);
    stage.append(wrap);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn btn-ghost inspect-close';
    close.textContent = 'Fermer';

    const hint = document.createElement('p');
    hint.className = 'inspect-hint';
    hint.textContent = 'Bouge la souris ou fais glisser le doigt sur la carte';

    overlay.append(stage, hint, close);
    document.body.append(overlay);
    document.body.classList.add('is-inspecting');

    const card = wrap.querySelector('.card');
    card.dataset.tilt = String(TILT_DEGREES);
    const rarity = rarityOf(wrap);
    if (SPARKLES[rarity] || wrap.classList.contains('is-shiny')) addSparkles(card.querySelector('.card-body'), SPARKLES[rarity] ?? SHINY_SPARKLES);

    // Sans pointeur sur la carte (écran tactile au repos), elle se balance doucement jusqu'au premier contact.
    let sway = null;
    const stopSway = () => { if (sway) cancelAnimationFrame(sway); sway = null; };
    if (!prefersReducedMotion()) {
        const tick = (time) => {
            const x = 0.5 + Math.sin(time * SWAY_SPEED) * 0.32;
            const y = 0.5 + Math.cos(time * SWAY_SPEED * 0.8) * 0.28;
            card.style.setProperty('--mx', `${x * 100}%`);
            card.style.setProperty('--my', `${y * 100}%`);
            card.style.setProperty('--rx', `${(0.5 - y) * TILT_DEGREES}deg`);
            card.style.setProperty('--ry', `${(x - 0.5) * TILT_DEGREES}deg`);
            sway = requestAnimationFrame(tick);
        };
        sway = requestAnimationFrame(tick);
    }
    stage.addEventListener('pointerdown', stopSway);
    stage.addEventListener('pointerenter', stopSway);

    const shut = () => {
        stopSway();
        document.removeEventListener('keydown', onKey, true);
        document.body.classList.remove('is-inspecting');
        overlay.remove();
        opener?.focus?.();
    };
    const onKey = (event) => {
        if (event.key === 'Escape') { event.stopPropagation(); shut(); }
        else if (event.key === 'Tab') { event.preventDefault(); close.focus(); }
    };
    document.addEventListener('keydown', onKey, true);
    overlay.addEventListener('click', (event) => { if (!stage.contains(event.target)) shut(); });
    close.addEventListener('click', shut);
    close.focus();
}

export function initInspector() {
    document.addEventListener('click', (event) => {
        const slot = event.target.closest?.('[data-inspect]');
        if (slot) open(slot);
    });
    document.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches?.('[data-inspect]')) {
            event.preventDefault();
            open(event.target);
        }
    });
}
