// Composition du deck : fonctions pures (aucun accès à l'écran) + mémoire du dernier choix du joueur.

const STORAGE_KEY = 'cyberdeck.choice';

// Règles de composition : `rarityOf` (Map id => rareté) et `caps` (rareté => nombre maximum dans un deck, voir config/game.php).
export const NO_RULES = { rarityOf: new Map(), caps: {} };

/** Vrai si on peut ajouter la carte sans dépasser la limite de sa rareté. */
export function canAdd(deck, id, rules = NO_RULES) {
    const rarity = rules.rarityOf.get(id);
    const cap = rules.caps[rarity];
    return cap === undefined || deck.filter((cardId) => rules.rarityOf.get(cardId) === rarity).length < cap;
}

/** Nombre de cartes de chaque rareté limitée dans le deck : { legendary: 2, … }. */
export const rarityCounts = (deck, rules) =>
    Object.fromEntries(Object.keys(rules.caps).map((rarity) => [rarity, deck.filter((id) => rules.rarityOf.get(id) === rarity).length]));

/** Remplit un deck avec les cartes classées dans l'ordre, en sautant celles qui dépasseraient une limite de rareté. */
function fillFrom(ranked, size, rules) {
    const deck = [];
    for (const id of ranked) {
        if (deck.length >= size) break;
        if (canAdd(deck, id, rules)) deck.push(id);
    }
    return deck;
}

/** Ajoute ou retire une carte ; on ne dépasse jamais `size` cartes ni la limite d'une rareté. */
export function toggle(deck, id, size, rules = NO_RULES) {
    if (deck.includes(id)) return deck.filter((cardId) => cardId !== id);
    return deck.length < size && canAdd(deck, id, rules) ? [...deck, id] : deck;
}

/** `size` cartes au hasard parmi `ids` (mélange de Fisher-Yates : tous les tirages sont équiprobables). */
export function randomDeck(ids, size, rules = NO_RULES) {
    const shuffled = [...ids];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return fillFrom(shuffled, size, rules);
}

// Les stratégies reçoivent toutes `valuesById` : Map id de carte => valeurs de ses attributs (dans l'ordre de config/game.php).
const total = (values) => values.reduce((sum, value) => sum + value, 0);

/** Les `size` premiers ids selon un score décroissant (dans la limite des raretés). */
const bestBy = (valuesById, size, score, rules) =>
    fillFrom([...valuesById].sort((a, b) => score(b[1]) - score(a[1])).map(([id]) => id), size, rules);

/** Les cartes les plus fortes, tous attributs confondus. */
export const strongestDeck = (valuesById, size, rules = NO_RULES) => bestBy(valuesById, size, total, rules);

/** Spécialiste : la plus haute moyenne sur un seul attribut ; à égalité, la carte la plus forte globalement. */
export const specialistDeck = (valuesById, size, attribute, rules = NO_RULES) =>
    bestBy(valuesById, size, (values) => values[attribute] * 1000 + total(values), rules);

/**
 * Équilibré : on ajoute à chaque fois la carte qui relève le plus l'attribut le plus faible du deck,
 * pour avoir un avantage partout plutôt qu'un gros point fort et un gros trou.
 */
export function balancedDeck(valuesById, size, rules = NO_RULES) {
    const remaining = new Map(valuesById);
    const sums = Array.from({ length: [...remaining.values()][0]?.length ?? 0 }, () => 0);
    const deck = [];

    while (deck.length < size && remaining.size > 0) {
        for (const id of remaining.keys()) if (!canAdd(deck, id, rules)) remaining.delete(id); // rareté au maximum : plus de candidates
        if (remaining.size === 0) break;
        const weakestAfter = (values) => Math.min(...values.map((value, i) => sums[i] + value));
        // Comparaison numérique : comparer deux tableaux avec « > » les convertirait en texte ("5,50" > "10,10").
        const beats = (a, b) => weakestAfter(a) - weakestAfter(b) || total(a) - total(b);
        const [bestId, bestValues] = [...remaining].reduce((best, entry) => (beats(entry[1], best[1]) > 0 ? entry : best));
        deck.push(bestId);
        bestValues.forEach((value, i) => { sums[i] += value; });
        remaining.delete(bestId);
    }

    return deck;
}

/** Moyenne de chaque attribut sur les cartes du deck (0 si le deck est vide). @param {number[][]} rows */
export function averages(rows, attributeCount) {
    return Array.from({ length: attributeCount }, (_, i) =>
        rows.length ? Math.round(rows.reduce((sum, row) => sum + row[i], 0) / rows.length) : 0);
}

// Le navigateur peut refuser le stockage (navigation privée) : on ignore l'échec, ce n'est qu'un confort.
export function loadChoice() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) ?? {};
    } catch {
        return {};
    }
}

export function saveChoice(choice) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(choice));
    } catch { /* voir ci-dessus */ }
}
