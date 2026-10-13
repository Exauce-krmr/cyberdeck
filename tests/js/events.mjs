// Moments clés et répliques de l'ordinateur : node tests/js/events.mjs
import assert from 'node:assert/strict';
import { gameEvent } from '../../public/js/game/events.js';
import { foeLine, isLimitEnd, verdict } from '../../public/js/game/text.js';

const g = (o) => ({ status: 'playing', round: 5, max_rounds: 20, player_count: 8, ai_count: 8, pot: 0, ...o });

assert.equal(gameEvent(null, g({})), null);
assert.equal(gameEvent(g({}), g({})), null, 'même manche : rien');
assert.equal(gameEvent(g({ round: 18 }), g({ round: 19 })).title, 'Dernière manche');
assert.equal(gameEvent(g({ round: 16, player_count: 9 }), g({ round: 17, player_count: 10 })).title, 'Plus que 3 manches');
assert.equal(gameEvent(g({ player_count: 3 }), g({ round: 6, player_count: 2, ai_count: 12 })).tone, 'danger');
assert.equal(gameEvent(g({ ai_count: 3 }), g({ round: 6, ai_count: 2, player_count: 12 })).title, 'Le système vacille');
assert.equal(gameEvent(g({ player_count: 8, ai_count: 9 }), g({ round: 6, player_count: 10, ai_count: 7 })).title, 'Tu passes en tête');
assert.equal(gameEvent(g({ player_count: 9, ai_count: 8 }), g({ round: 6, player_count: 7, ai_count: 10 })).tone, 'danger');
assert.equal(gameEvent(g({}), g({ round: 6, pot: 4 })).tone, 'pot');
assert.equal(gameEvent(g({ round: 1 }), g({ round: 2, player_count: 7, ai_count: 9 })), null, 'égalité de départ : pas de « reprend l\'avance »');

// Fin au nombre de manches : le verdict porte sur les piles, pas sur la dernière manche
const lastRound = g({ round: 19 });
assert.equal(gameEvent(lastRound, g({ round: 20, status: 'win', player_count: 18, ai_count: 2 })).title, 'Victoire aux cartes');
assert.equal(gameEvent(lastRound, g({ round: 20, status: 'lose', player_count: 3, ai_count: 15 })).tone, 'danger');
assert.equal(gameEvent(lastRound, g({ round: 20, status: 'draw', player_count: 10, ai_count: 10 })).title, 'Égalité parfaite');
assert.equal(gameEvent(lastRound, g({ round: 12, status: 'win', player_count: 20, ai_count: 0 })).title, 'Partie gagnée', 'pile adverse vide : la partie est gagnée, on le dit');
assert.equal(gameEvent(lastRound, g({ round: 12, status: 'lose', player_count: 0, ai_count: 20 })).title, 'Partie perdue');
assert.equal(isLimitEnd(g({ round: 20, status: 'win', player_count: 18, ai_count: 2 })), true);
assert.equal(isLimitEnd(g({})), false);
assert.match(foeLine('revealed', { outcome: 'ai' }, { round: 3, final: 'win' }), /Verdict|Défaite|Marge/);

// L'étiquette sous les cartes dit qui a gagné la PARTIE quand elle s'arrête aux cartes
assert.deepEqual(verdict('player', { outcome: 'ai' }, 'win'), { text: 'Victoire', won: true });
assert.deepEqual(verdict('ai', { outcome: 'ai' }, 'win'), { text: 'Défaite', won: false });
assert.equal(verdict('player', { outcome: 'player' }, 'lose').text, 'Défaite');
assert.equal(verdict('ai', { outcome: 'player' }, 'draw').text, 'Égalité');
assert.equal(verdict('player', { outcome: 'ai' }).text, 'Perdue', 'sans fin aux cartes : verdict de la manche');

const seen = new Set();
for (let round = 0; round < 20; round++) seen.add(foeLine('choosing', null, { round }));
assert.ok(seen.size >= 4, 'les répliques varient');
for (const line of seen) assert.ok(!/\bje\b|\bj'/i.test(line), `pas de « je » : ${line}`);
assert.ok(foeLine('choosing', { outcome: 'ai' }, { round: 3 }).length > 0);
console.log('events ok');

// derived.js : l'ambiance de la table est calculée par des fonctions pures
const { tensionOf, foeStressOf, endgameOf, shareOf, moodOf } = await import('../../public/js/game/derived.js');
const live = (extra) => ({ status: 'playing', round: 3, max_rounds: 20, player_count: 10, ai_count: 10, pot: 0, ...extra });
assert.equal(tensionOf(null), 'none');
assert.equal(tensionOf(live({ player_count: 2, ai_count: 18 })), 'danger', 'deux cartes ou moins : danger');
assert.equal(tensionOf(live({ player_count: 7, ai_count: 13 })), 'tense');
assert.equal(tensionOf(live({ player_count: 15, ai_count: 5 })), 'winning');
assert.equal(tensionOf(live({ player_count: 10, ai_count: 10 })), 'calm');
assert.equal(tensionOf(live({ status: 'win' })), 'none');
assert.equal(foeStressOf(live({ ai_count: 0, status: 'win' })), 'down');
assert.equal(foeStressOf(live({ ai_count: 2, player_count: 18 })), 'critical');
assert.equal(foeStressOf(live({ ai_count: 6, player_count: 14 })), 'strained');
assert.equal(foeStressOf(live()), 'ok');
assert.equal(endgameOf(live({ round: 19 })), 'last');
assert.equal(endgameOf(live({ round: 17 })), 'soon');
assert.equal(endgameOf(live({ round: 5 })), 'none');
assert.equal(shareOf(live({ pot: 0 }), 'player_count', null), '50.0');
assert.equal(shareOf(live({ pot: 5 }), 'pot', null), '20.0');
assert.equal(shareOf(live({ pot: 5, status: 'win' }), 'pot', 'win'), '0.0', 'partie finie : la cagnotte ne compte plus');
assert.equal(moodOf(null, null, 'choosing'), 'none');
assert.equal(moodOf(live(), null, 'answering'), 'foe');
assert.equal(moodOf(live(), { outcome: 'player' }, 'revealed'), 'win');
