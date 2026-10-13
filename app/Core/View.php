<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Leaf\Blade;

/** Rend une vue Blade ; les variables communes à toutes les pages sont ajoutées ici une seule fois. */
final class View
{
    private Blade $blade;

    /** @param Closure(): array<string, mixed> $shared évaluée à chaque rendu (jeton CSRF, utilisateur…) */
    public function __construct(string $viewsPath, string $cachePath, private Closure $shared)
    {
        $this->blade = new Blade($viewsPath, $cachePath);
    }

    /** @param array<string, mixed> $data */
    public function render(string $name, array $data = []): string
    {
        return $this->blade->render($name, $data + ($this->shared)());
    }
}
