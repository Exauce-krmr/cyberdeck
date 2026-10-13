// Collection : équiper ou retirer le skin shiny d'une carte (le serveur garde l'état ; ici on bascule l'affichage).
import { api, ApiError } from '../core/api.js';

export function initShinyToggle() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest?.('[data-shiny-toggle]');
        if (!button || button.disabled) return;

        button.disabled = true;
        try {
            const { on } = await api('POST', '/api/collection/shiny', { card: Number(button.dataset.shinyToggle) });
            button.setAttribute('aria-pressed', String(on));
            button.previousElementSibling?.querySelector('.card-wrap')?.classList.toggle('is-shiny', on);
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            button.textContent = error.message;
        } finally {
            button.disabled = false;
        }
    });
}

/**
 * Les cartes de la collection n'animent leurs reflets que si elles sont à l'écran : 60 cartes shiny qui brillent en même temps, dont la plupart
 * hors de vue, ne servent à rien. Un calque (IntersectionObserver) met l'animation en marche ou en pause via --shiny-play.
 */
export function watchVisibility() {
    const cards = document.querySelectorAll('.collection-grid .card-wrap');
    if (!cards.length || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => entry.target.style.setProperty('--shiny-play', entry.isIntersecting ? 'running' : 'paused'));
    }, { rootMargin: '120px' });
    cards.forEach((card) => observer.observe(card));
}
