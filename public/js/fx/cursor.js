// Curseur cyberpunk : un point qui vise (sans retard) et un réticule qui le suit d'un léger délai.
// La forme dépend de ce qui est survolé (voir css/base/cursor.css › États). Rien ne s'active sans souris :
// écran tactile → on garde le comportement natif.

const FINE_POINTER = '(hover: hover) and (pointer: fine)';
const FOLLOW = 0.28; // part de la distance rattrapée à chaque image par le réticule (1 = aucun retard)

// Ordre = priorité : le premier sélecteur qui correspond à un ancêtre du survol décide de l'état.
const STATES = [
    // Les attributs d'une carte sont des boutons désactivés hors partie : de la décoration, pas un interdit (d'où :not(.stat)).
    ['deny', ':disabled:not(.stat), [aria-disabled="true"], .is-blocked, .cursor-not-allowed, label:has(input:disabled)'],
    ['text', 'input:not([type="checkbox"], [type="radio"], [type="submit"], [type="button"], [type="range"]), textarea, [contenteditable="true"]'],
    ['zoom', '[data-inspect]'],
    ['click', 'a[href], button:not(:disabled), summary, select, label[for], label:has(input:not(:disabled)), .cursor-pointer, [role="button"], [role="tab"], input[type="checkbox"], input[type="radio"], input[type="submit"], input[type="button"], input[type="range"], .hand-slot.is-answerable, .deck-card, .reveal-back:not([data-teasing="true"]), .opening-pack-wrap[data-phase="present"], .legend-screen'],
    ['help', '[data-help]'], // élément non cliquable : le curseur devient « ? » (sur un élément cliquable, seule la bulle s'ajoute)
];

function stateOf(target) {
    if (!(target instanceof Element)) return 'default';
    for (const [state, selector] of STATES) {
        if (target.closest(selector)) return state;
    }

    return 'default';
}

function build() {
    const root = document.createElement('div');
    root.className = 'cy-cursor';
    root.setAttribute('aria-hidden', 'true');
    root.innerHTML = '<i class="cy-pulse"></i><i class="cy-ring"><b></b><b></b><b></b><b></b></i><i class="cy-glyph"></i><i class="cy-dot"></i>';
    document.body.append(root);

    return root;
}

/** La bulle d'aide : suit le curseur tant qu'il survole un élément portant data-help. */
function buildTip() {
    const tip = document.createElement('div');
    tip.className = 'cy-tip';
    tip.setAttribute('aria-hidden', 'true');
    document.body.append(tip);

    return tip;
}

export function initCursor() {
    const query = window.matchMedia(FINE_POINTER);
    if (!query.matches) return;

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const root = build();
    const ring = root.querySelector('.cy-ring');
    const tip = buildTip();
    let x = 0, y = 0, lagX = 0, lagY = 0, frame = null, state = 'default';

    const draw = () => {
        lagX += (x - lagX) * (reduced ? 1 : FOLLOW);
        lagY += (y - lagY) * (reduced ? 1 : FOLLOW);
        root.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        ring.style.setProperty('--rx', `${(lagX - x).toFixed(2)}px`); // le réticule est décalé du retard accumulé, le point reste sur la souris
        ring.style.setProperty('--ry', `${(lagY - y).toFixed(2)}px`);
        frame = Math.abs(x - lagX) + Math.abs(y - lagY) > 0.1 ? requestAnimationFrame(draw) : null;
    };
    const setState = (next) => {
        if (next === state) return;
        state = next;
        root.dataset.state = next;
    };
    const showTip = (target) => {
        const source = target.closest?.('[data-help]'); // la bulle s'affiche sur tout élément qui a data-help, cliquable ou non
        const text = source?.dataset.help;
        if (!text) { tip.dataset.on = 'false'; return; }
        if (tip.dataset.source !== text + source.dataset.helpTone) { // un titre en gras (data-help-title, facultatif) puis l'explication, qui passe à la ligne
            tip.dataset.source = text + source.dataset.helpTone;
            tip.replaceChildren();
            if (source.dataset.helpTitle) tip.append(Object.assign(document.createElement('strong'), { textContent: source.dataset.helpTitle }));
            // Une phrase d'explication, puis à la ligne ce qu'il faut retenir (la suite, en rouge).
            const [first, ...rest] = text.split(/(?<=\.)\s+/);
            tip.append(Object.assign(document.createElement('p'), { textContent: first }));
            if (rest.length) tip.append(Object.assign(document.createElement('p'), { className: source.dataset.helpTone === 'warn' ? 'cy-tip-warn' : 'cy-tip-info', textContent: rest.join(' ') }));
        }
        tip.dataset.on = 'true';
        const margin = 14, box = tip.getBoundingClientRect();
        const left = Math.min(x + 26, window.innerWidth - box.width - margin);
        const top = y + 30 + box.height > window.innerHeight - margin ? y - box.height - 22 : y + 30; // trop bas : la bulle passe au-dessus du curseur
        tip.style.transform = `translate3d(${Math.max(margin, left)}px, ${Math.max(margin, top)}px, 0)`;
    };

    document.addEventListener('pointermove', (event) => {
        if (event.pointerType === 'touch') return;
        if (root.dataset.visible !== 'true') { lagX = x = event.clientX; lagY = y = event.clientY; root.dataset.visible = 'true'; }
        x = event.clientX;
        y = event.clientY;
        setState(stateOf(event.target));
        showTip(event.target);
        frame ??= requestAnimationFrame(draw);
    }, { passive: true });

    document.addEventListener('pointerdown', () => {
        root.dataset.down = 'true';
        root.dataset.pulse = 'false';
        void root.offsetWidth; // relance l'animation de l'onde
        root.dataset.pulse = 'true';
    }, { passive: true });
    document.addEventListener('pointerup', () => { root.dataset.down = 'false'; }, { passive: true });
    document.documentElement.addEventListener('pointerleave', () => { root.dataset.visible = 'false'; tip.dataset.on = 'false'; });
    window.addEventListener('blur', () => { root.dataset.visible = 'false'; });

    // L'état peut changer sans que la souris bouge (un bouton se désactive, un chargement démarre) : on réévalue à intervalle lent.
    setInterval(() => { if (root.dataset.visible === 'true') { const el = document.elementFromPoint(x, y); if (el) { setState(stateOf(el)); showTip(el); } } }, 250);

    document.documentElement.classList.add('cy-on'); // en dernier : si quoi que ce soit a échoué plus haut, le curseur natif reste
}
