<?php

declare(strict_types=1);

namespace App\Services\Dev;

/**
 * Qui a le droit d'entrer dans le Labo. Deux verrous, tous deux obligatoires :
 *  1. l'interrupteur DEV_TOOLS=1 (absent = tout le Labo est invisible, même pour un administrateur) ;
 *  2. le pseudo connecté doit figurer dans DEV_USERS (liste séparée par des virgules).
 * Le pseudo vient de la session serveur : un visiteur ne peut ni le choisir ni le falsifier.
 */
final class DevAccess
{
    /** @param string[] $users pseudos autorisés */
    public function __construct(private bool $enabled, private array $users)
    {
    }

    public function allows(?string $username): bool
    {
        if (!$this->enabled || $username === null || $username === '') {
            return false;
        }
        foreach ($this->users as $allowed) {
            if (strcasecmp($allowed, $username) === 0) { // les pseudos sont uniques sans tenir compte de la casse en base
                return true;
            }
        }

        return false;
    }
}
