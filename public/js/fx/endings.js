// Fins de partie : explosion de l'ordinateur, effondrement du joueur, extinctions « aux cartes ».
import { animate, canAnimate, centerOf, overlay, rand, screenFlash } from './core.js';
import { hit } from './basic.js';

const MELTDOWN_DELAY_MS = 1200; // le temps de voir la carte adverse perdre, puis l'ordinateur panique
const MELTDOWN_TITLE_DELAY_S = 0.3;
const MELTDOWN_TITLE_S = 4.6; // « Système hors ligne » reste assez longtemps pour être lu et savouré
const SPARKS = 26;
const DEBRIS = 16;
const EMBERS = 22;
const SHARDS = [ // éclats de carte et de circuit : formes variées plutôt que des rectangles
    'polygon(0 12%, 70% 0, 100% 55%, 55% 100%, 8% 85%)',
    'polygon(50% 0, 100% 80%, 0 100%)',
    'polygon(0 0, 100% 25%, 80% 100%, 10% 70%)',
    'polygon(20% 0, 100% 10%, 85% 90%, 0 100%)',
];
const FIRE = ['#ffffff', '#ffd27a', '#ff9a3d', '#ff4d5e'];

/** Explosion en un point de l'écran : boule de feu, ondes de choc, étincelles allongées et débris sombres qui retombent. */
function explodeAt({ x, y }, scale = 1) {
    const add = (className, css) => {
        const node = document.createElement('i');
        node.className = className;
        node.setAttribute('aria-hidden', 'true');
        node.style.cssText = `left:${x}px;top:${y}px;${css}`;
        document.body.append(node);
        setTimeout(() => node.remove(), 3200);
        return node;
    };

    animate(add('blast-core', `width:${9 * scale}rem;height:${9 * scale}rem;`), { scale: [0.1, 1.5], opacity: [1, 0] }, { duration: 0.55, ease: 'easeOut' });
    [0, 0.12].forEach((delay) => animate(add('blast-ring', `width:${6 * scale}rem;height:${6 * scale}rem;`), { scale: [0.2, 3.2], opacity: [0.9, 0] }, { duration: 0.8, delay, ease: 'easeOut' }));

    for (let i = 0; i < SPARKS * scale; i++) {
        const angle = Math.random() * Math.PI * 2;
        const power = rand(140, 460) * scale;
        const spark = add('blast-spark', `height:${rand(0.8, 2.4)}rem;background:${FIRE[i % FIRE.length]};rotate:${angle + Math.PI / 2}rad;`);
        animate(spark, { x: Math.cos(angle) * power, y: Math.sin(angle) * power, opacity: [1, 1, 0], scaleY: [1, 0.2] }, { duration: rand(0.45, 0.9), ease: 'easeOut' });
    }
    // Débris : lancés vers le haut puis retombent en arc (gravité), tournent en 3D, rougeoient avant de s'éteindre.
    for (let i = 0; i < DEBRIS * scale; i++) {
        const angle = rand(-Math.PI * 0.95, -Math.PI * 0.05);
        const reach = rand(90, 360) * scale;
        const peak = Math.sin(angle) * rand(150, 340) * scale;
        const size = rand(0.9, 2.8);
        const debris = add('blast-debris', `width:${size * rand(1, 1.6)}rem;height:${size}rem;clip-path:${SHARDS[i % SHARDS.length]};--glow:${FIRE[(i % 3) + 1]};`);
        animate(debris, {
            x: [0, Math.cos(angle) * reach * 0.6, Math.cos(angle) * reach],
            y: [0, peak, 320 + rand(0, 80)],
            rotate: rand(-720, 720),
            rotateX: rand(-360, 360),
            opacity: [1, 1, 0],
            filter: ['brightness(2.2)', 'brightness(1)', 'brightness(0.5)'],
        }, { duration: rand(1.5, 2.3), ease: ['easeOut', 'easeIn'], times: [0, 0.4, 1] });
    }
    // Braises : petits points incandescents qui montent lentement et vacillent.
    for (let i = 0; i < EMBERS * scale; i++) {
        const ember = add('blast-ember', `width:${rand(0.18, 0.4)}rem;height:${rand(0.18, 0.4)}rem;background:${FIRE[i % FIRE.length]};`);
        animate(ember, { x: [0, rand(-90, 90), rand(-150, 150)], y: [0, rand(-120, -220), rand(-260, -420)], opacity: [0, 1, 0.5, 1, 0], scale: [1, 1.3, 0.3] }, { duration: rand(1.6, 2.8), delay: rand(0.1, 0.5), ease: 'easeOut' });
    }
}

/** Fin de partie gagnée : le VS s'éteint et ton camp glisse au milieu du terrain (le camp adverse garde sa place, vide). */
function centerWinner(arena) {
    const vs = arena.querySelector('.arena-vs');
    const side = arena.querySelector('.arena-player');
    if (vs) animate(vs, { scale: [1, 0], opacity: [1, 0], rotate: [0, 120] }, { duration: 0.45, ease: 'easeIn' }).then?.(() => { vs.style.visibility = 'hidden'; });
    if (!side) return;
    const shift = centerOf(arena).x - centerOf(side).x;
    animate(side, { x: [0, shift] }, { duration: 0.8, delay: 0.25, ease: [0.22, 1, 0.36, 1] });
}

/**
 * Dernière carte de l'ordinateur perdue : il panique, puis sa carte, sa plaque et son compteur explosent, et le système tombe.
 * `after` : promesse de revealRound (l'annonce plein écran d'une légendaire doit d'abord disparaître).
 * @returns {Promise<void>} résolue quand « Système hors ligne » a disparu : c'est seulement là que la notification « Partie gagnée » peut apparaître
 */
export function meltdown(arena, after = Promise.resolve()) {
    const slot = arena?.querySelector('.arena-ai .card-slot');
    if (!slot) return Promise.resolve();
    const hudScore = document.querySelectorAll('.hud-score')[1];
    const tag = arena.querySelector('.foe-tag');
    if (!canAnimate) { slot.classList.add('is-destroyed'); return Promise.resolve(); }

    return after.then(() => new Promise((resolve) => {
        arena.classList.add('is-meltdown'); // la panique commence une fois l'écran dégagé
        setTimeout(() => {
            const targets = [[slot, 1.15], [tag, 0.7], [hudScore, 0.5]].filter(([element]) => element);
            const centers = targets.map(([element, size]) => [centerOf(element), size]);
            slot.classList.add('is-destroyed');
            tag?.classList.add('is-destroyed');
            hudScore?.classList.add('is-destroyed');
            centers.forEach(([center, size], index) => setTimeout(() => explodeAt(center, size), index * 140));

            screenFlash('blast-flash', { duration: 0.7, peak: 0.12, lifeMs: 800 });
            animate(arena, { x: [0, -16, 14, -10, 8, -4, 0], y: [0, 6, -6, 4, -2, 0, 0] }, { duration: 0.6 });

            const title = document.createElement('div');
            title.className = 'meltdown-title';
            title.setAttribute('aria-hidden', 'true');
            title.innerHTML = '<strong>Système hors ligne</strong><span>Plus aucune carte</span>';
            document.body.append(title);
            animate(title, { opacity: [0, 1, 1, 0], scale: [1.7, 1, 1, 1] }, { duration: MELTDOWN_TITLE_S, delay: MELTDOWN_TITLE_DELAY_S, times: [0, 0.06, 0.88, 1] });
            setTimeout(() => hit(title.querySelector('strong')), 450);
            setTimeout(() => title.remove(), (MELTDOWN_TITLE_S + 0.5) * 1000);
            setTimeout(() => centerWinner(arena), 900); // la fumée retombe : le VS s'éteint et ta carte prend le centre
            setTimeout(resolve, (MELTDOWN_TITLE_DELAY_S + MELTDOWN_TITLE_S) * 1000); // « Système hors ligne » a disparu : la notification peut apparaître
        }, MELTDOWN_DELAY_MS);
    }));
}

const GLITCH_BOARD_MS = 1300; // le plateau se corrompt d'abord
const GLITCH_SCREEN_MS = 1500; // puis tout l'écran
const SLICES = 9;

/**
 * Défaite par manque de cartes : glitch total du plateau, puis de tout l'écran, « connexion perdue » et extinction façon écran cathodique.
 * `after` : promesse de revealRound (l'annonce plein écran d'une légendaire doit d'abord disparaître). `onDone` : appelé à l'extinction (on passe à l'écran de défaite).
 */
export function defeat(arena, after = Promise.resolve(), onDone = () => {}) {
    if (!arena) return;
    const hudScore = document.querySelectorAll('.hud-score')[0];
    if (!canAnimate) { hudScore?.classList.add('is-destroyed'); return; }

    after.then(() => {
        // 1. le plateau se corrompt : tremblements, séparation RVB, bandes décalées
        arena.classList.add('is-corrupt');
        document.querySelector('.hud')?.classList.add('is-corrupt');
        document.querySelector('.dock')?.classList.add('is-corrupt');
        const slices = Array.from({ length: SLICES }, () => `<i style="top:${rand(0, 95)}%;height:${rand(2, 12)}%;animation-delay:${rand(0, 0.6)}s;--shift:${rand(-14, 14)}%"></i>`).join('');
        overlay('glitch-slices', slices, GLITCH_BOARD_MS + GLITCH_SCREEN_MS + 1200);

        // 2. tout l'écran : parasites, flashs inversés, puis le message
        setTimeout(() => {
            document.body.classList.add('is-crashing');
            overlay('crash-noise', '', GLITCH_SCREEN_MS + 1800);
            hudScore?.classList.add('is-destroyed');

            setTimeout(() => {
                document.body.classList.remove('is-crashing');
                // Le message s'installe et RESTE (fond opaque) : le plateau ne doit plus jamais réapparaître avant l'écran de défaite.
                const crash = overlay('crash-screen', '<strong>Connexion perdue</strong><span>Tu n\'as plus aucune carte</span>', 5000);
                animate(crash, { opacity: [0, 1] }, { duration: 0.15 });
                setTimeout(() => hit(crash.querySelector('strong')), 200);

                // 3. les parasites s'intensifient jusqu'à tout couvrir
                const storm = overlay('crash-noise is-intense', '', 5000);
                storm.style.opacity = 0;
                setTimeout(() => {
                    animate(storm, { opacity: [0, 1] }, { duration: 0.6, ease: 'easeIn' });
                    animate(crash.querySelectorAll('strong, span'), { opacity: 0 }, { duration: 0.3 }); // le message disparaît avant l'écran blanc : il ne doit jamais se revoir
                }, 1300);

                // 4. extinction par-dessus le brouillard, puis on change d'écran en cachette et on dévoile la défaite
                setTimeout(() => {
                    const off = overlay('crt-off', '', 1000);
                    animate(off, { scaleY: [1, 0.008, 0.008], opacity: [1, 1, 0] }, { duration: 0.9, times: [0, 0.55, 1], ease: 'easeIn' });
                    document.querySelectorAll('.is-corrupt').forEach((node) => node.classList.remove('is-corrupt'));
                    setTimeout(onDone, 450);
                    setTimeout(() => {
                        [crash, storm].forEach((node) => animate(node, { opacity: 0 }, { duration: 0.6 }));
                    }, 1050);
                }, 2000);
            }, GLITCH_SCREEN_MS);
        }, GLITCH_BOARD_MS);
    });
}

const LOSS_PULSE_MS = 700;   // en continu : toutes les 700 ms, 1 à 2 composants au hasard glitchent, jusqu'à la fin de la partie
let lossPulse = null;
const OFF_BEAT_MS = 900;      // on lit le résultat de la dernière manche avant l'extinction

/** Retire tout ce que les fins de partie ont posé sur l'écran (appelé quand on passe à l'écran de résultat ou qu'on relance). */
export function clearEndEffects() {
    clearInterval(lossPulse);
    document.querySelectorAll('.loss-veil, .glitch-slices').forEach((node) => node.remove());
    document.querySelectorAll('.is-poweroff, .is-corrupt, .is-gone').forEach((node) => node.classList.remove('is-poweroff', 'is-corrupt', 'is-gone'));
}

/**
 * Défaite aux cartes (il t'en reste, mais moins que l'ordinateur) : version légère de l'effondrement.
 * Un voile rouge discret, et ce sont les composants (cartes, plaques, compteurs, bandeau) qui glitchent, pas l'écran :
 * deux ou trois à la fois, au hasard, puis ça s'arrête et le rouge reste.
 */
export function limitDefeat(arena, after = Promise.resolve()) {
    if (!arena || !canAnimate) return;
    after.then(() => setTimeout(() => {
        const veil = overlay('loss-veil', '', 600000); // reste jusqu'à clearEndEffects()
        animate(veil, { opacity: [0, 1] }, { duration: 0.8, ease: 'easeOut' });

        const targets = [...arena.querySelectorAll('.card-slot, .you-tag, .foe-tag, .arena-vs, .verdict'), ...document.querySelectorAll('.hud-score, .hud-mid, .banner, .dock-go')];
        // En continu tant que la partie n'est pas quittée (clearEndEffects l'arrête) : un ou deux composants à la fois, jamais l'écran entier.
        clearInterval(lossPulse);
        lossPulse = setInterval(() => {
            [...targets].sort(() => Math.random() - 0.5).slice(0, 1 + Math.round(Math.random())).forEach((node) => hit(node));
        }, LOSS_PULSE_MS);
    }, OFF_BEAT_MS));
}

/**
 * Victoire aux cartes (l'ordinateur en a encore, mais moins que toi) : il s'éteint. Sa carte se réduit à une ligne, sa plaque
 * et son compteur se grisent, puis ton camp prend le centre.
 */
export function limitWin(arena, after = Promise.resolve()) {
    if (!arena || !canAnimate) return;
    after.then(() => setTimeout(() => {
        const off = [arena.querySelector('.arena-ai .card-slot'), arena.querySelector('.foe-tag'), document.querySelectorAll('.hud-score')[1]].filter(Boolean);
        off.forEach((node) => node.classList.add('is-poweroff'));
        arena.classList.add('is-poweroff'); // masque aussi son étiquette « Victoire / Défaite »
        setTimeout(() => centerWinner(arena), 1100);
        setTimeout(() => arena.querySelector('.arena-ai')?.classList.add('is-gone'), 1900); // sur mobile, sa place se referme
    }, OFF_BEAT_MS));
}

/**
 * Choisit la séquence de fin qui correspond à l'état de la partie (rien si elle continue).
 * `after` : promesse de revealRound. `onDefeatDone` : appelé à l'extinction d'une défaite par manque de cartes.
 * @returns {Promise<void>} résolue quand la notification de fin peut s'afficher sans cacher la séquence : après l'explosion de l'ordinateur, sinon dès `after`
 */
export function playEndSequence(arena, game, after, onDefeatDone) {
    const { status, player_count: player, ai_count: ai } = game;
    if (ai === 0) return meltdown(arena, after);
    if (player === 0) defeat(arena, after, onDefeatDone);
    else if (status === 'win') limitWin(arena, after);
    else if (status === 'lose') limitDefeat(arena, after);

    return after;
}
