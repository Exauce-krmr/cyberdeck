// « Une seule requête à la fois ; une erreur devient un message à l'écran, jamais une exception perdue. »
// Mélangé aux composants Alpine qui parlent à l'API (`...busyMixin`) : ils partagent `busy`, `error` et `run()`.
import { ApiError } from './api.js';

export const busyMixin = {
    busy: false,
    error: '',

    async run(action) {
        if (this.busy) return;
        this.busy = true;
        this.error = '';
        try {
            await action();
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.error = error.message;
        } finally {
            this.busy = false;
        }
    },
};
