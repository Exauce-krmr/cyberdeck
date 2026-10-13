// Petits formateurs de texte français partagés par les composants.

/** « 1 carte », « 3 cartes » : `word` prend un s au pluriel (0 et 1 sont au singulier). */
export const plural = (count, word) => `${count} ${word}${count > 1 ? 's' : ''}`;
