// Glitch des titres (PowerGlitch) : chaque élément `.glitch` décroche seul, à des moments aléatoires.
// Purement décoratif : sans la librairie, ou avec « réduire les animations », la page est identique, sans effet.
const PAUSE_MS = [5000, 14000]; // attente avant le prochain glitch, tirée au hasard pour chaque élément
const BURST_MS = [250, 600];    // durée d'un glitch

const OPTIONS = {
    hideOverflow: true, // les tranches décalées ne doivent jamais faire apparaître de barres de défilement
    playMode: 'manual', // on déclenche nous-mêmes, pour tirer chaque délai au hasard
    shake: { velocity: 15, amplitudeX: 0.08, amplitudeY: 0.08 },
    slice: { count: 4, velocity: 15, minHeight: 0.02, maxHeight: 0.1, hueRotate: true },
};

const between = ([min, max]) => min + Math.random() * (max - min);

function glitchLater(glitch) {
    setTimeout(() => {
        glitch.startGlitch();
        setTimeout(() => {
            glitch.stopGlitch();
            glitchLater(glitch);
        }, between(BURST_MS));
    }, between(PAUSE_MS));
}

export function initGlitch() {
    const lib = window.PowerGlitch;
    if (!lib || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    // Un appel par élément : chacun a son propre minuteur, donc les titres ne glitchent jamais tous ensemble.
    document.querySelectorAll('.glitch').forEach((element) => glitchLater(lib.glitch(element, OPTIONS)));
}
