// Tests des fonctions pures de composition de deck : `node tests/js/deck.mjs` (aucune dépendance).
import assert from 'node:assert/strict';
import { averages, balancedDeck, canAdd, randomDeck, rarityCounts, specialistDeck, strongestDeck, toggle } from '../../public/js/game/deck.js';

const cards = new Map([[1, [5, 50, 50, 50, 50]], [2, [10, 10, 10, 10, 10]], [3, [90, 5, 5, 5, 5]]]);

assert.deepEqual(balancedDeck(cards, 1), [2], 'équilibré : compare des nombres, pas du texte ("5" > "10")');
assert.deepEqual(strongestDeck(cards, 1), [1], 'les plus fortes : total le plus haut');
assert.deepEqual(specialistDeck(cards, 1, 0), [3], 'spécialiste : meilleur sur l\'attribut demandé');
assert.equal(new Set(randomDeck([1, 2, 3, 4, 5], 3)).size, 3, 'au hasard : pas de doublon');
assert.deepEqual(toggle([1, 2], 3, 2), [1, 2], 'on ne dépasse pas la taille du deck');
assert.deepEqual(toggle([1, 2], 2, 2), [1], 'cliquer une carte déjà choisie la retire');
assert.deepEqual(averages([[10, 20], [30, 40]], 2), [20, 30]);
assert.deepEqual(averages([], 2), [0, 0]);

// Limites de rareté : 1 légendaire au plus, la carte 3 (la plus forte) est une légendaire.
const rules = { rarityOf: new Map([[1, 'common'], [2, 'common'], [3, 'legendary'], [4, 'legendary']]), caps: { legendary: 1 } };
const big = new Map([...cards, [4, [80, 5, 5, 5, 5]]]);
assert.equal(canAdd([3], 4, rules), false, 'limite atteinte : la 2e légendaire est refusée');
assert.equal(canAdd([3], 1, rules), true, 'une rareté sans limite passe toujours');
assert.deepEqual(toggle([3], 4, 5, rules), [3], 'toggle respecte la limite');
assert.deepEqual(strongestDeck(big, 3, rules), [1, 3, 2], 'les plus fortes : saute la 2e légendaire');
assert.deepEqual(specialistDeck(big, 2, 0, rules), [3, 2], 'spécialiste : saute la 2e légendaire');
assert.equal(balancedDeck(big, 3, rules).filter((id) => rules.rarityOf.get(id) === 'legendary').length <= 1, true, 'équilibré : une seule légendaire');
assert.equal(randomDeck([1, 2, 3, 4], 4, rules).length, 3, 'au hasard : une légendaire écartée');
assert.deepEqual(rarityCounts([3, 1], rules), { legendary: 1 });

console.log('deck.js : 16 vérifications passées');
