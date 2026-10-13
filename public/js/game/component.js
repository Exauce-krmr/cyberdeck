// Composant Alpine de la page de jeu : il orchestre, il ne décide rien.
//   - les règles sont côté serveur (le navigateur envoie un choix et affiche la réponse)
//   - la phase courante et les actions permises sont dans phase.js
//   - les textes dans text.js, les raccourcis dans shortcuts.js, les animations dans fx/animations.js
import { api } from '../core/api.js';
import { busyMixin } from '../core/busy.js';
import { canAnimate } from '../fx/core.js';
import { bump, clearEndEffects, countTo, flipIn, hit, playEndSequence, revealRound } from '../fx/animations.js';
import { Phase, isAllowed, phaseOf } from './phase.js';
import { averages, balancedDeck, canAdd, loadChoice, rarityCounts, randomDeck, saveChoice, specialistDeck, strongestDeck, toggle } from './deck.js';
import { shortcutFor } from './shortcuts.js';
import { banner, foeLine, isLimitEnd, rewardLine, verdict } from './text.js';
import { gameEvent } from './events.js';
import { calloutMixin } from './callout.js';
import { endgameOf, foeStressOf, moodOf, shareOf, tensionOf } from './derived.js';

const DOCK_SLIDE_MS = 350; // sortie des réserves et du bouton, puis remontée du bouton au centre
const ABANDON_CONFIRM_MS = 3000; // temps laissé pour confirmer l'abandon

export default () => ({
    ...busyMixin,    // busy, error, run()
    ...calloutMixin, // callout, lastCallout, showEvent()
    game: null,    // état renvoyé par le serveur (null = pas de partie)
    result: null,  // dernière manche, avec les deux cartes révélées
    loaded: false,
    shown: { player: 0, ai: 0 }, // compteurs affichés, qui défilent vers les vraies valeurs
    keyboardFocus: false, // le cadre pointillé n'apparaît qu'une fois les touches du clavier utilisées
    focusHand: 0,     // carte de la main visée au clavier (flèches gauche / droite)
    confirmAbandon: false, // « Abandonner » demande une seconde confirmation
    dockSolo: false, // le dock ne garde que le bouton du résultat (après la sortie des réserves)
    holdEnd: false, // vrai tant qu'une annonce légendaire masque la fin de partie
    scanArmed: false, // Scan choisi : le prochain attribut cliqué est scanné au lieu d'être joué

    // Composition du deck (écran de préparation)
    deck: [],
    difficulty: 'normal',
    cardValues: new Map(), // id de carte => valeurs de ses attributs, lues dans la page
    cardRarity: new Map(), // id de carte => rareté, lue dans la page
    deckCaps: {},          // rareté => nombre maximum dans un deck (config/game.php)
    deckSize: 0,
    attributeCount: 0,

    // ---- Lecture (utilisée par la vue)
    /** Partie telle qu'on la montre : tant que l'annonce d'une légendaire joue, la fin de partie reste cachée (statut « en cours »). */
    get view() { return this.holdEnd && this.game ? { ...this.game, status: 'playing' } : this.game; },
    get phase() { return phaseOf({ loaded: this.loaded, game: this.view, result: this.result, busy: this.busy, scanArmed: this.scanArmed }); },
    get banner() { return banner(this.phase, { game: this.view, result: this.result }); },
    get averages() { return averages(this.deck.map((id) => this.cardValues.get(id)), this.attributeCount); },
    /** Vrai pendant la partie : le header se cache et la page prend toute la largeur. */
    get immersive() { return Boolean(this.game && (this.game.status === 'playing' || this.result)); },
    /** Ambiance du plateau (data-mood, voir css/play.css) : suit ce qui se passe dans la manche. */
    get mood() { return moodOf(this.view, this.result, this.phase); },
    /** 'danger' | 'tense' | 'calm' | 'winning' : pilote l'ambiance de la table (voile rouge qui bat, jauge d'alarme). */
    get tension() { return tensionOf(this.view); },
    /** Statut de la partie ('win' | 'lose' | 'draw') dès qu'elle est finie, quelle que soit la façon (KO ou manches), sinon null. */
    get over() { return this.view && this.view.status !== 'playing' ? this.view.status : null; },
    /** Étiquette sous une carte : une fois la partie finie, elle dit qui gagne la PARTIE (pas la dernière manche). */
    verdict(side) { return this.holdEnd ? { text: '', won: false } : verdict(side, this.result, this.over); },
    /** Statut final ('win' | 'lose' | 'draw') quand la partie s'arrête au nombre de manches, sinon null. */
    get limitEnd() { return isLimitEnd(this.view) ? this.view.status : null; },
    get rewardText() { return rewardLine(this.view); },
    get foeSays() { return foeLine(this.phase, this.result, { round: this.game?.round ?? 0, stress: this.foeStress, final: this.limitEnd }); },
    /** 'ok' | 'strained' | 'critical' | 'down' : l'ordinateur « stresse » quand il perd ses cartes (étiquette rouge qui tremble). */
    get foeStress() { return (this.holdEnd && this.game?.ai_count === 0 ? 'critical' : foeStressOf(this.view)); },
    /** Fin de partie qui approche : 'soon' (3 manches ou moins) | 'last' (dernière) | 'none'. */
    get endgame() { return endgameOf(this.view); },
    get roundsLeft() { return this.game ? this.game.max_rounds - this.game.round : 0; },

    /** Part (en %) d'un compteur dans le total des cartes en jeu, pour la jauge. */
    share(counter) { return shareOf(this.game, counter, this.over); },

    // ---- Actions : chacune vérifie d'abord qu'elle est permise dans la phase courante
    async init() {
        this.readDeckChoices();
        this.$watch('view.status', (status) => this.settleDock(status));
        await this.run(() => this.syncGame('GET', '/api/game/state'));
        this.loaded = true;
    },

    async startGame() {
        if (!this.can('start')) return;
        saveChoice({ deck: this.deck, difficulty: this.difficulty });
        await this.run(async () => {
            this.result = null;
            clearEndEffects();
            await this.syncGame('POST', '/api/game/start', { difficulty: this.difficulty, deck: this.deck });
            await this.$nextTick();
            flipIn(this.$refs.arena);
        });
    },

    /** Abandon en deux temps (un seul clic ne détruit pas la partie) ; il compte comme une défaite. */
    async abandon() {
        if (!this.can('abandon')) return;
        if (!this.confirmAbandon) {
            this.confirmAbandon = true;
            setTimeout(() => { this.confirmAbandon = false; }, ABANDON_CONFIRM_MS);
            return;
        }
        await this.run(async () => {
            await api('POST', '/api/game/abandon');
            this.setGame(null);
            this.result = null;
        });
        this.confirmAbandon = false;
    },

    /** Règles de composition lues dans la page : limites par rareté. */
    get deckRules() { return { rarityOf: this.cardRarity, caps: this.deckCaps }; },
    get rarityCounts() { return rarityCounts(this.deck, this.deckRules); },
    /** Une carte est bloquée quand sa rareté est au maximum dans le deck (et qu'elle n'y est pas déjà). */
    isBlocked(id) { return !this.deck.includes(id) && !canAdd(this.deck, id, this.deckRules); },
    toggleCard(id) { this.deck = toggle(this.deck, id, this.deckSize, this.deckRules); },
    clearDeck() { this.deck = []; },
    /** Composition automatique du deck, selon la stratégie choisie dans le menu (`attribute` : indice, pour « spécialiste »). */
    autoDeck(strategy, attribute = 0) {
        const decks = {
            random: () => randomDeck([...this.cardValues.keys()], this.deckSize, this.deckRules),
            balanced: () => balancedDeck(this.cardValues, this.deckSize, this.deckRules),
            strongest: () => strongestDeck(this.cardValues, this.deckSize, this.deckRules),
            specialist: () => specialistDeck(this.cardValues, this.deckSize, attribute, this.deckRules),
        };
        this.deck = decks[strategy]();
    },

    /** Capacités : le Scan s'arme (il se paie au clic sur l'attribut), les autres s'appliquent tout de suite. */
    async useAbility(name) {
        if (!this.can('ability')) return;
        if (name === 'scan' && this.phase !== Phase.ANSWERING) {
            this.scanArmed = !this.scanArmed;
            return;
        }
        await this.run(async () => {
            await this.syncGame('POST', '/api/game/ability', { ability: name });
            await this.$nextTick();
            if (name === 'swap') flipIn(this.$refs.arena.querySelector('.arena-player'));
        });
    },
    /** En défense (l'ordinateur mène) : pas de Boost, et le Scan se joue d'un clic, sans choisir d'attribut (il porte sur l'attribut annoncé). */
    canUseAbility(name) {
        if (!this.can('ability') || this.game.abilities[name] < 1) return false;
        if (name === 'boost') return !this.game.boost && this.phase !== Phase.ANSWERING;
        return true;
    },
    isArmed(name) { return name === 'scan' ? this.scanArmed : name === 'boost' && this.game.boost; },

    async scanAttribute(attribute) {
        await this.run(async () => {
            await this.syncGame('POST', '/api/game/ability', { ability: 'scan', attribute });
            this.scanArmed = false;
        });
    },

    /** Clic dans la main : les cartes sont injectées en HTML, on délègue l'événement. */
    onCardClick(event) {
        const slot = event.target.closest('[data-hand]');
        if (!slot) return;
        const button = event.target.closest('[data-attr]');

        if (this.can('scan') && button) {
            this.scanAttribute(button.dataset.attr);
        } else if (this.phase === Phase.CHOOSING && button) {
            button.classList.add('is-picked'); // retour immédiat, avant la réponse du serveur
            this.chargeIfLegendary(slot);
            this.resolveRound(Number(slot.dataset.hand), button.dataset.attr);
        } else if (this.phase === Phase.ANSWERING) {
            this.chargeIfLegendary(slot);
            this.resolveRound(Number(slot.dataset.hand), null);
        }
    },

    async next() {
        if (!this.can('next')) return;
        this.result = null;
        clearEndEffects();
        await this.$nextTick();
        flipIn(this.$refs.arena ?? this.$refs.reward);
    },

    onKey(event) {
        const shortcut = shortcutFor(event, this.phase);
        if (shortcut?.action === 'focus' || shortcut?.action === 'pick') this.keyboardFocus = true;
        if (shortcut?.action === 'next') this.next();
        if (shortcut?.action === 'cancelScan') this.scanArmed = false;
        if (shortcut?.action === 'focus') this.focusHand = Math.min(Math.max(this.focusHand + shortcut.delta, 0), this.game.hand_html.length - 1);
        if (shortcut?.action === 'pick') {
            const slot = this.$refs.arena.querySelectorAll('[data-hand]')[this.phase === Phase.ANSWERING ? shortcut.index : this.focusHand];
            (this.phase === Phase.ANSWERING ? slot : slot?.querySelectorAll('[data-attr]:not(:disabled)')[shortcut.index])?.click();
        }
    },

    // ---- Interne
    /** Fin de partie : les réserves et le bouton glissent vers le bas (CSS), puis le dock se réduit et le bouton remonte par le milieu. */
    settleDock(status) {
        if (status === 'playing') { this.dockSolo = false; return; }
        setTimeout(() => {
            this.dockSolo = true;
            const button = this.$refs.dockGo;
            if (button && canAnimate) {
                button.animate({ transform: ['translateY(130%)', 'translateY(0)'], opacity: [0, 1] }, { duration: DOCK_SLIDE_MS, easing: 'ease-out' });
            }
        }, DOCK_SLIDE_MS);
    },

    /** Les cartes du deck sont rendues par le serveur : on lit leurs valeurs dans la page, puis on restaure le dernier choix. */
    readDeckChoices() {
        const cards = [...this.$root.querySelectorAll('[data-card-id]')];
        this.cardValues = new Map(cards.map((el) => [Number(el.dataset.cardId), el.dataset.values.split(',').map(Number)]));
        this.attributeCount = cards[0]?.dataset.values.split(',').length ?? 0;
        this.deckSize = Number(this.$root.dataset.deckSize);
        this.cardRarity = new Map(cards.map((el) => [Number(el.dataset.cardId), el.dataset.rarity]));
        this.deckCaps = JSON.parse(this.$root.dataset.deckLimits || '{}');

        const saved = loadChoice();
        // Le deck mémorisé est rejoué carte par carte : une limite de rareté changée depuis ne laisse pas passer un deck interdit.
        this.deck = (saved.deck ?? []).filter((id) => this.cardValues.has(id)).reduce((deck, id) => toggle(deck, id, this.deckSize, this.deckRules), []);
        this.difficulty = saved.difficulty ?? this.difficulty;
    },

    can(action) { return isAllowed(this.phase, action); },

    /** Appelle l'API et range la partie renvoyée. */
    async syncGame(method, path, body) {
        this.setGame((await api(method, path, body)).game);
    },

    /** La légendaire « se charge » avant de partir. */
    chargeIfLegendary(slot) {
        if (slot.querySelector('.rarity-legendary')) slot.classList.add('is-charging');
    },

    async resolveRound(hand, attribute) {
        await this.run(async () => {
            const data = await api('POST', '/api/game/play', { hand, attribute });
            this.result = data.result;
            let release = () => {};
            // Une légendaire gagnante est annoncée plein écran : la fin de partie (défaite, verdict, ambiance) attend qu'elle ait disparu.
            this.holdEnd = data.result.outcome === 'player'; // levé aussitôt si aucune légendaire n'est annoncée
            this.setGame(data.game, new Promise((resolve) => { release = resolve; })); // les événements attendent la fin de l'annonce de la légendaire
            await this.$nextTick();
            // Les séquences de fin attendent que l'annonce de la légendaire (s'il y en a une) ait disparu.
            const settled = revealRound(this.$refs.arena, data.result.outcome);
            settled.then(() => { this.holdEnd = false; });
            // Les événements attendent la fin de la séquence qui domine l'écran (annonce légendaire, puis explosion de l'ordinateur).
            playEndSequence(this.$refs.arena, data.game, settled, () => this.next()).then(release); // la défaite enchaîne directement sur l'écran de fin
        });
    },

    /** `gate` : promesse qui doit être résolue avant d'afficher un événement (on ne le montre pas sous l'annonce d'une légendaire). */
    setGame(game, gate = Promise.resolve()) {
        const event = gameEvent(this.game, game);
        // Un coup sans événement (Scan, Boost…) ne doit pas effacer le bandeau en cours : on ne le retire que pour un nouvel événement ou une autre partie.
        if (event || !game || !this.game || game.round < this.game.round) this.showEvent(event, gate);
        this.game = game;
        this.focusHand = 0;
        this.keyboardFocus = false;
        this.confirmAbandon = false;
        const { player_count: player = 0, ai_count: ai = 0 } = game ?? {};
        const delta = { player: player - this.shown.player, ai: ai - this.shown.ai };
        countTo(this.shown.player, player, (value) => { this.shown.player = value; });
        countTo(this.shown.ai, ai, (value) => { this.shown.ai = value; });
        this.flashScores(delta);
    },

    /** Les compteurs de cartes font un coup de zoom quand ils changent, et glitchent quand ils baissent. */
    flashScores(delta) {
        if (!this.$nextTick) return;
        this.$nextTick(() => {
            const [mine, foe] = this.$root.querySelectorAll('.hud-score > span');
            [[mine, delta.player], [foe, delta.ai]].forEach(([element, change]) => {
                if (!element || change === 0) return;
                bump(element);
                if (change < 0) hit(element);
            });
        });
    },
});
