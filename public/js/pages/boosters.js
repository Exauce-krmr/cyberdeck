// Page Boosters : stock, cérémonie d'ouverture, achat d'un booster shiny. Le serveur décide de tout (tirage, points) ;
// ici on ne fait que mettre en scène le résultat. Les effets de lumière sont dans fx/booster-fx.js.
import { api } from '../core/api.js';
import { busyMixin } from '../core/busy.js';
import { plural } from '../core/format.js';
import { burst, canAnimate, centerOf, flash, inflow, shake, shockwave, sparks } from '../fx/booster-fx.js';
import { animate } from '../fx/core.js';
import { addSparkles } from '../fx/inspector.js';

const ROMAN = ['I', 'II', 'III', 'IV', 'V'];
const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary'];
const CHARGE_MS = 1500;        // le paquet se charge au moins ce temps (le serveur répond pendant)
const LADDER = RARITY_ORDER; // l'aura grimpe cet escalier pendant le suspense
const STEP_MS = [0, 650, 800, 1000]; // temps passé sur chaque palier
const FAKE_CHANCE = 0.06;      // part des cartes qui font croire à mieux avant de retomber : vraiment rare, et au plus une fois par paquet
const FALL_MS = 700;           // la chute d'une fausse promesse
const AFTER_FLIP_MS = { common: 500, rare: 600, epic: 900, legendary: 1600, shiny: 1600 };
const SPARKLE_COUNT = 34;
const SHINY_SPARKLE_COUNT = 14; // le skin shiny scintille moins qu'un légendaire : le cadre nacré fait déjà le travail
const TITLES = { common: 'Nouvelle carte', rare: 'Rare !', epic: 'Épique !', legendary: 'Légendaire !', shiny: 'Shiny !' };
const EMPTY_TITLE = ''; // booster shiny sans skin : aucune phrase, seules les pièces gagnées tombent
const MAX_COINS = 12;          // au-delà, les pièces ne tiennent plus à l'écran : le total reste dans la note

export default () => ({
    ...busyMixin, // busy, error, run()
    stock: [],
    points: 0,
    unlocked: false,
    finished: false, // toutes les cartes ET tous les skins shiny : plus aucun booster à gagner
    converted: 0,
    meta: { labels: {}, contents: {}, chance: {}, cost: 0 },
    notice: '',
    opening: null, // { pack, phase: 'present' | 'charge' | 'tear' | 'reveal' | 'done', tone, dark, rays, title, items: [{ html, rarity, shiny, flipped, aura, teasing }], note }
    pending: null, // résultat du serveur, mis en scène carte par carte
    teasing: false, // une carte est en plein suspense : on n'en retourne pas deux à la fois

    init() {
        this.meta = JSON.parse(this.$el.dataset.meta);
        this.apply(JSON.parse(this.$el.dataset.overview));
        if (this.converted) this.notice = this.convertedText(this.converted);
    },

    apply(overview) {
        this.stock = overview.stock;
        this.points = overview.points;
        this.unlocked = overview.unlocked;
        this.finished = overview.finished;
        this.converted = overview.converted;
    },

    convertedText(count) {
        const s = count > 1 ? 's' : '';

        return `${count} booster${s} devenu${s} inutile${s} (tout est déjà obtenu) : transformé${s} en pièces shiny.`;
    },

    // ---- Habillage d'un paquet, calculé depuis la config
    tone: (pack) => (pack.kind === 'shiny' ? 'shiny' : pack.difficulty),
    title(pack) { return pack.kind === 'shiny' ? 'Shiny' : this.meta.labels[pack.difficulty]; },
    // Un paquet shiny porte autant d'étoiles que le rang de sa difficulté (1, 2, 3) : la valeur se lit sans le moindre texte.
    level(pack) { return pack.kind === 'shiny' ? '✦'.repeat(Object.keys(this.meta.labels).indexOf(pack.difficulty) + 1) : ROMAN[Object.keys(this.meta.labels).indexOf(pack.difficulty)] ?? ''; },
    // Bulle d'aide du niveau d'un paquet (voir fx/cursor.js) : une phrase de contenu, puis ce qui le limite ou le renforce.
    helpTitle(pack) { return pack.kind === 'shiny' ? `Booster shiny · ${'✦'.repeat(Object.keys(this.meta.labels).indexOf(pack.difficulty) + 1)}` : `Booster ${this.meta.labels[pack.difficulty]}`; },
    helpTone(pack) { return pack.kind !== 'shiny' && !this.meta.contents[pack.difficulty].legendary ? 'warn' : 'info'; }, // « aucune légendaire » est une limite : en rouge
    helpText(pack) {
        if (pack.kind === 'shiny') return `Il contient au plus un skin shiny, pour une carte que tu possèdes déjà. Il a ${this.meta.chance[pack.difficulty] ?? 0} % de chance d'en donner un, sinon tu reçois des pièces shiny.`;
        const content = this.meta.contents[pack.difficulty];
        const extra = content.boosted ? 'Les raretés élevées y sont plus probables.' : 'Les raretés y sont tirées normalement.';

        return `Il contient ${plural(content.cards, 'carte')}. ${content.legendary ? extra : 'Aucune légendaire possible.'}`;
    },
    describe(pack) {
        if (pack.kind === 'shiny') return `${this.meta.chance[pack.difficulty] ?? 0} % de chance`;
        const content = this.meta.contents[pack.difficulty];

        return `${plural(content.cards, 'carte')}${content.legendary ? '' : ' · sans légendaire'}${content.boosted ? ' · chances boostées' : ''}`;
    },

    async buyShiny() {
        await this.run(async () => {
            this.apply((await api('POST', '/api/boosters/buy-shiny')).overview);
            this.notice = 'Booster shiny ajouté à ton stock.';
        });
    },

    // ---- La cérémonie : présentation → (clic) charge → déchirement → cartes retournées une à une (clic)
    nap(ms) {
        return canAnimate ? new Promise((resolve) => setTimeout(resolve, ms)) : Promise.resolve();
    },

    /** Ouvre la scène : le paquet est montré en grand. Rien n'est consommé tant qu'on ne l'a pas touché. */
    open(pack) {
        if (this.busy) return;
        this.notice = '';
        this.error = '';
        this.pending = null;
        this.teasing = false;
        this.opening = { pack, phase: 'present', tone: this.tone(pack), dark: false, rays: false, shiny: false, glint: false, faked: false, arriving: false, title: '', best: -1, items: [], note: '', coins: 0, earned: 0 };
        this.$nextTick(() => { if (canAnimate && this.$refs.pack) animate(this.$refs.pack, { scale: [0.4, 1], opacity: [0, 1], rotateY: [160, 0] }, { duration: 0.7, ease: [0.2, 0.9, 0.3, 1.1] }); });
    },

    /** Le clic sur le paquet : le serveur tire les cartes pendant que le paquet se charge, puis il se déchire. */
    async launch() {
        if (this.opening?.phase !== 'present') return;
        await this.run(async () => {
            this.opening.phase = 'charge';
            const request = api('POST', '/api/boosters/open', { id: this.opening.pack.id });
            request.then((data) => this.foresee(data.opened), () => {}); // le halo prend la couleur de la meilleure carte pendant la charge ; l'erreur est traitée plus bas
            const [data] = await Promise.all([request, this.charge()]).catch((error) => { this.opening.phase = 'present'; throw error; });

            this.pending = { ...data.opened, titles: data.unlocked?.titles ?? [] };
            this.apply(data.overview); // le paquet est consommé côté serveur : le stock suit tout de suite
            await this.tear(this.tone(this.opening.pack));
            await this.deal(data.opened);
        });
    },

    /**
     * Pressentiment : si le paquet contient une épique ou mieux, des éclats de lumière blanche scintillent autour de lui pendant la charge.
     * Volontairement identique pour une épique et une légendaire (et rien pour le reste) : on devine qu'il y a quelque chose, pas quoi.
     */
    foresee(opened) {
        if (!this.opening || this.opening.phase === 'present') return;
        const ranks = opened.rarities.map((rarity) => LADDER.indexOf(rarity));
        this.opening.glint = opened.kind === 'shiny' ? opened.cards_html.length > 0 : Math.max(-1, ...ranks) >= LADDER.indexOf('epic');
    },

    /** Le paquet gonfle et vibre de plus en plus fort (trémulation à ~25 Hz : fluide, pas saccadée) en aspirant de l'énergie. */
    async charge() {
        const wrap = this.$refs.pack;
        const point = centerOf(wrap);
        if (canAnimate) {
            const steps = 38;
            const shiver = (amp) => Array.from({ length: steps }, (_, i) => (i % 2 ? -1 : 1) * amp * (i / steps) ** 1.6 * (0.6 + Math.random() * 0.4));
            animate(wrap, { x: shiver(11), y: shiver(5), rotate: shiver(3.2), scale: [1, 1.12] }, { duration: CHARGE_MS / 1000, ease: 'linear' });
        }
        const timer = setInterval(() => inflow(point, this.opening?.tone ?? 'normal', 3), 70);
        await this.nap(CHARGE_MS);
        clearInterval(timer);
    },

    /** Le paquet se déchire : un trait de lumière s'ouvre au milieu, les deux moitiés glissent en s'écartant, la lumière jaillit. */
    async tear(tone) {
        this.opening.phase = 'tear';
        const point = centerOf(this.$refs.pack);
        this.packPoint = point; // d'où les cartes jaillissent
        if (canAnimate) {
            const ease = [0.5, 0, 0.2, 1];
            animate(this.$refs.pack, { scale: [1.12, 1.16, 1.0] }, { duration: 0.9, ease: 'easeOut' });
            animate(this.$refs.top, { y: [0, -innerHeight * 0.55], rotate: [0, -5], opacity: [1, 1, 0] }, { duration: 0.8, delay: 0.12, ease });
            animate(this.$refs.bottom, { y: [0, innerHeight * 0.55], rotate: [0, 4], opacity: [1, 1, 0] }, { duration: 0.8, delay: 0.12, ease });
            animate(this.$refs.beam, { opacity: [0, 1, 1, 0], scaleX: [0.1, 1, 1.3, 1.3], scaleY: [1, 1, 5, 40] }, { duration: 0.9, ease: 'easeOut', times: [0, 0.2, 0.55, 1] });
            setTimeout(() => { flash(tone); shockwave(point, tone, 10); sparks(point, tone, 36, 1.2); }, 150);
        }
        await this.nap(900);
    },

    /** Les cartes montent dos visible, toutes pareilles : rien ne trahit leur rareté. Au joueur de les toucher. */
    async deal(opened) {
        const shiny = opened.kind === 'shiny';
        this.opening.arriving = opened.cards_html.length > 0; // le pied de page reste caché tant que les cartes n'ont pas fini d'arriver
        this.opening.phase = 'reveal';
        // La vraie rareté de la carte : un skin shiny sur une commune reste une commune (le shiny a son propre éclat).
        this.opening.items = opened.cards_html.map((html, index) => ({ html, rarity: opened.rarities[index], shiny, flipped: false, aura: 'idle', teasing: false }));
        if (!opened.cards_html.length) { // booster shiny vide
            this.opening.title = EMPTY_TITLE;
            this.finish();
            return;
        }
        await this.$nextTick();
        if (canAnimate) this.flyOut().then(() => { this.opening && (this.opening.arriving = false); });
        else this.opening.arriving = false;
    },

    /**
     * Les cartes jaillissent du paquet : elles partent de son centre, décrivent un arc en tournant, puis se posent.
     * @returns {Promise<void>} résolue quand la dernière est posée
     */
    flyOut() {
        const origin = this.packPoint;
        const slots = [...this.$root.querySelectorAll('.reveal-slot')];
        const landings = slots.map((slot, index) => {
            const to = centerOf(slot);
            const dx = (origin?.x ?? innerWidth / 2) - to.x;
            const dy = (origin?.y ?? innerHeight / 2) - to.y;
            const spin = (index % 2 ? 1 : -1) * (14 + index * 5);
            slot.style.opacity = '0'; // sans ça, une carte au délai long reste visible à sa place avant de partir
            const flight = animate(slot, {
                x: [dx, dx * 0.35, 0],
                y: [dy, Math.min(dy, 0) - 90 + dy * 0.2, 0],
                scale: [0.25, 1.12, 1],
                rotate: [spin * 3, spin * 0.6, 0],
                rotateY: [-180, -40, 0],
                opacity: [0, 1, 1],
            }, { duration: 0.95, delay: index * 0.13, ease: [0.2, 0.8, 0.3, 1], times: [0, 0.6, 1] });
            setTimeout(() => { if (slot.isConnected) sparks(centerOf(slot), 'common', 6, 0.4); }, 600 + index * 130);

            return Promise.resolve(flight?.finished ?? flight).catch(() => {}).then(() => slot.style.removeProperty('opacity'));
        });

        return Promise.all(landings).then(() => {});
    },

    slotAt: (root, index) => root.querySelectorAll('.reveal-slot')[index],

    /** Le joueur touche une carte : l'aura grimpe, parfois jusqu'au légendaire… puis la vérité. */
    async pick(index) {
        const item = this.opening?.items[index];
        if (!item || item.flipped || this.teasing || this.opening.phase !== 'reveal') return;
        this.teasing = true;
        item.teasing = true;
        const slot = this.slotAt(this.$root, index);
        const tone = item.rarity; // la scène garde toujours la couleur de la VRAIE rareté ; le shiny n'ajoute qu'une lueur nacrée par-dessus

        await this.suspense(item, slot, tone);
        item.teasing = false;
        await this.flip(index, slot, tone, item.shiny);
        const rank = item.shiny ? LADDER.length : LADDER.indexOf(item.rarity);
        if (rank > this.opening.best) { this.opening.best = rank; this.opening.title = item.shiny ? TITLES.shiny : TITLES[tone]; this.titlePop(); }
        await this.nap(AFTER_FLIP_MS[item.shiny ? 'shiny' : tone]);

        this.teasing = false;
        if (this.opening.items.every((other) => other.flipped)) this.finish();
    },

    finish() {
        this.opening.note = this.noteFor(this.pending);
        this.opening.phase = 'done';
        this.dropCoins(this.pending?.points ?? 0);
    },

    coinsCaption() {
        const earned = this.opening?.earned ?? 0;
        const text = `+${plural(earned, 'pièce')} shiny`;

        return this.opening?.items.length ? text : `Pas de skin cette fois : ${text}`;
    },

    coinsHelp() {
        return this.unlocked
            ? `Avec ${this.meta.cost} pièces, tu peux acheter un booster shiny.`
            : `Verrouillées : avec ${this.meta.cost} pièces, tu pourras acheter un booster shiny une fois toutes les cartes obtenues.`;
    },

    /** Les pièces shiny gagnées (les « points » du serveur) tombent (à la place du vide d'un booster shiny raté, ou en plus des cartes). */
    async dropCoins(points) {
        if (points <= 0 || !this.opening) return;
        this.opening.earned = points;
        this.opening.coins = Math.min(points, MAX_COINS);
        await this.$nextTick();
        if (!canAnimate) return;
        const coins = [...this.$root.querySelectorAll('.opening-coins .coin')];
        coins.forEach((coin, index) => {
            coin.style.opacity = '0'; // caché tant que sa chute n'a pas commencé (sinon on la voit déjà posée pendant le délai)
            const fall = animate(coin, { y: [-260, 0, -18, 0], rotateY: [0, 720], opacity: [0, 1] }, { duration: 1, delay: 0.15 + index * 0.12, ease: 'easeOut', times: [0, 0.55, 0.78, 1] });
            Promise.resolve(fall?.finished ?? fall).catch(() => {}).then(() => coin.style.removeProperty('opacity'));
            setTimeout(() => { if (coin.isConnected) sparks(centerOf(coin), 'shiny', 7, 0.35); }, 150 + index * 120 + 550);
        });
        setTimeout(() => this.opening && flash('shiny'), 600);
    },

    /** Teinte la carte et la scène d'un palier. Le légendaire allume les rayons ; en dessous, la scène reste sombre. */
    setAura(item, aura) {
        item.aura = aura;
        const high = aura === 'legendary' || aura === 'shiny';
        this.opening.tone = aura === 'idle' ? this.tone(this.opening.pack) : aura;
        this.opening.dark = high || aura === 'epic';
        this.opening.rays = high;
    },

    /**
     * Le suspense : l'aura monte palier par palier. Avec FAKE_CHANCE elle dépasse la vraie rareté (on croit au légendaire),
     * puis retombe d'un coup. Le shiny ne change rien ici : l'attente suit la vraie rareté (sinon elle trahirait le skin).
     */
    async suspense(item, slot, tone) {
        const real = LADDER.indexOf(tone);
        const fakes = !this.opening.faked && real < LADDER.length - 1 && Math.random() < FAKE_CHANCE;
        if (fakes) this.opening.faked = true;
        const peak = fakes ? real + 1 + Math.floor(Math.random() * (LADDER.length - 1 - real)) : real;
        const path = LADDER.slice(1, peak + 1);
        const center = () => centerOf(slot);

        if (canAnimate) animate(slot, { scale: [1, 1.12] }, { duration: 0.3, ease: 'easeOut' });
        await this.nap(350);

        for (const [index, aura] of path.entries()) {
            const step = index + 1;
            this.setAura(item, aura);
            if (canAnimate) {
                shockwave(center(), aura, 4 + step * 2);
                sparks(center(), aura, 6 + step * 6, 0.5 + step * 0.3);
                shake(slot, 0.3 + step * 0.4);
                flash(aura);
            }
            await this.nap(STEP_MS[Math.min(step, STEP_MS.length - 1)] + (step === path.length ? 500 : 0));
        }

        if (peak > real) await this.fall(item, slot, LADDER[real]); // fausse promesse : retombée
        else if (path.length === 0) await this.nap(500);           // une commune ne promet rien : courte attente
        if (canAnimate) animate(slot, { scale: [1.12, 1] }, { duration: 0.25 });
    },

    /** La chute : l'aura retombe à la couleur de la vraie rareté, la carte s'affaisse. */
    async fall(item, slot, aura) {
        if (canAnimate) {
            sparks(centerOf(slot), aura, 14, 0.4); // les cendres gardent la couleur de la vraie rareté
            animate(slot, { y: [0, 14, 0], scale: [1.12, 0.92, 1.0], rotate: [0, -2, 0] }, { duration: FALL_MS / 1000, ease: 'easeOut' });
        }
        this.setAura(item, aura);
        await this.nap(FALL_MS);
    },

    /** Retournement : le dos se referme à plat, la face s'ouvre ; l'éclat part au moment où elle apparaît. */
    async flip(index, slot, tone, shiny = false) {
        if (canAnimate && slot) {
            animate(slot, { rotateY: [0, 90], scale: [1.1, 1.14] }, { duration: 0.18, ease: 'easeIn' });
            await this.nap(180);
        }
        this.opening.items[index].flipped = true;
        await this.$nextTick();
        if (canAnimate && slot) animate(slot, { rotateY: [-90, 0], scale: [1.14, 1] }, { duration: 0.35, ease: [0.2, 0.9, 0.3, 1.25] });

        this.opening.tone = tone;
        this.opening.shiny = shiny; // les rayons mêlent alors la couleur de la rareté et la nacre
        if (slot) {
            burst(tone, centerOf(slot), this.$refs.stage);
            if (shiny && canAnimate) { // jets de lumière nacrés, par-dessus la couleur de la rareté qui reste celle de la scène
                flash('shiny');
                shockwave(centerOf(slot), 'shiny', 7);
                sparks(centerOf(slot), 'shiny', 26, 0.9);
                this.opening.dark = true;
                this.opening.rays = true;
            }
            // Les légendaires et les shiny scintillent en continu ; la carte reste inclinable (holo.js) et inspectable après coup.
            if (tone === 'legendary' || shiny) addSparkles(slot.querySelector('.card-body'), tone === 'legendary' ? SPARKLE_COUNT : SHINY_SPARKLE_COUNT);
        }
    },

    titlePop() {
        this.$nextTick(() => { if (canAnimate && this.$refs.title) animate(this.$refs.title, { scale: [1.7, 1], opacity: [0, 1] }, { duration: 0.45, ease: 'easeOut' }); });
    },

    noteFor(opened) {
        const parts = [];
        if (opened.converted > 0) parts.push(this.convertedText(opened.converted));
        for (const title of opened.titles ?? []) parts.push(`Nouveau titre débloqué : ${title}`);

        return parts.join(' · ');
    },

    close() {
        if (!['present', 'done'].includes(this.opening?.phase)) return;
        this.opening = null;
    },
});
