// Le moment clé affiché un instant (changement de tête, danger, dernière manche) : état et minuteries, mélangés au composant de partie
// (`...calloutMixin`). Il ne bloque rien et se retire seul.

const CALLOUT_DELAY_MS = 300; // laisse la manche se révéler avant d'afficher l'événement
const CALLOUT_MS = 3500;

export const calloutMixin = {
    callout: null,     // moment clé affiché ({ tone, title, detail }), null entre deux
    lastCallout: null, // dernier moment clé : son texte reste en place pendant que le bandeau se referme
    calloutTimer: 0,
    calloutTicket: 0,  // numéro du dernier événement demandé

    /**
     * Remplace le moment clé en cours par `event` (null : rien de nouveau, le bandeau actuel est simplement retiré).
     * `gate` : promesse qui doit être résolue avant l'affichage (on ne le montre pas sous l'annonce d'une légendaire).
     */
    showEvent(event, gate = Promise.resolve()) {
        clearTimeout(this.calloutTimer); // annule aussi la minuterie qui masque le bandeau en cours
        this.callout = null;
        const ticket = ++this.calloutTicket; // une manche plus récente périme l'événement en attente
        if (!event) return;
        gate.then(() => {
            if (ticket !== this.calloutTicket) return;
            this.calloutTimer = setTimeout(() => {
                this.callout = event;
                this.lastCallout = event;
                this.calloutTimer = setTimeout(() => { this.callout = null; }, CALLOUT_MS);
            }, CALLOUT_DELAY_MS);
        });
    },
};
