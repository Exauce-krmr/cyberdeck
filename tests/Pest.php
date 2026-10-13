<?php

declare(strict_types=1);

// Configuration de Pest : chargée avant les tests. Pas de Leaf ni de MariaDB ici (voir tests/bootstrap.php).
require_once __DIR__ . '/bootstrap.php';

/** Enregistre un test Pest dont le résultat vient d'être calculé : les scénarios de jeu s'enchaînent dans l'ordre d'une partie. */
function check(string $name, bool $passed): void
{
    test($name, fn () => expect($passed)->toBeTrue());
}

/** Vrai si $action lève une exception de la classe attendue. */
function raises(callable $action, string $class): bool
{
    try {
        $action();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }

    return false;
}
