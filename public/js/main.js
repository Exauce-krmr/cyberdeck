// Point d'entrée : branche chaque module sur la page. Chargé avant Alpine (voir layouts/app.blade.php).
import gameComponent from './game/component.js';
import boostersComponent from './pages/boosters.js';
import labComponent from './pages/lab.js';
import { initShinyToggle, watchVisibility } from './pages/collection.js';
import { initGlitch } from './fx/glitch.js';
import { initHolo } from './fx/holo.js';
import { initInspector } from './fx/inspector.js';
import { initCursor } from './fx/cursor.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('game', gameComponent);
    window.Alpine.data('boosters', boostersComponent);
    window.Alpine.data('lab', labComponent);
});
initCursor();
initHolo();
initGlitch();
initInspector();
initShinyToggle();
watchVisibility();
