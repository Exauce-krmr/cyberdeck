// Inclinaison et reflet holographique : on ne fait que renseigner des variables CSS, app.css fait le reste.

const VARIABLES = ['--mx', '--my', '--rx', '--ry'];
const TILT_DEGREES = 14;

const holoCard = (event) => event.target.closest?.('.card.holo, .card[data-tilt], .pack-tile .pack, .pack-tilt, .reveal-back'); // cartes, boosters et dos de cartes : même inclinaison

/** Vrai quand le pointeur est au-dessus de la zone de cette carte, même si un autre élément (étiquette, voile…) est posé par-dessus à cet endroit. */
const isOver = (card, event) => {
    const rect = card.getBoundingClientRect();

    return event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
};

function tilt(card, event) {
    const degrees = Number(card.dataset.tilt) || TILT_DEGREES;
    const rect = card.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    const y = (event.clientY - rect.top) / rect.height;
    card.style.setProperty('--mx', `${x * 100}%`);
    card.style.setProperty('--my', `${y * 100}%`);
    card.style.setProperty('--rx', `${(0.5 - y) * degrees}deg`);
    card.style.setProperty('--ry', `${(x - 0.5) * degrees}deg`);
}

export function initHolo() {
    let active = null; // la dernière carte inclinée : on continue de la suivre tant que le pointeur reste dans sa zone

    document.addEventListener('pointermove', (event) => {
        const card = holoCard(event) ?? (active && active.isConnected && isOver(active, event) ? active : null);
        if (!card) return;
        active = card;
        tilt(card, event);
    });

    document.addEventListener('pointerout', (event) => {
        const card = holoCard(event);
        if (!card || card.contains(event.relatedTarget) || isOver(card, event)) return; // on ne remet à plat que lorsque le pointeur a vraiment quitté la carte
        VARIABLES.forEach((name) => card.style.removeProperty(name));
        if (active === card) active = null;
    });

    // Le pointeur sort de la zone sans qu'aucun évènement « out » ne touche la carte (un voile l'a recouverte) : on remet à plat.
    document.addEventListener('pointermove', (event) => {
        if (active && !isOver(active, event)) {
            VARIABLES.forEach((name) => active.style.removeProperty(name));
            active = null;
        }
    }, { passive: true });
}
