<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Seul endroit qui parle à Leaf pour répondre : si l'API de Leaf change, on ne corrige que ce fichier.
 */
final class Http
{
    public static function html(string $html, int $status = 200): void
    {
        response()->markup($html, $status);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): void
    {
        response()->json($data, $status);
    }

    public static function redirect(string $url): void
    {
        response()->redirect($url);
    }

    /**
     * Champs envoyés par un formulaire ou par un fetch() en JSON.
     *
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        $body = json_decode((string) file_get_contents('php://input'), true);

        return is_array($body) ? $body : $_POST;
    }

    /** Jeton CSRF : champ caché "_csrf" pour les formulaires, en-tête X-CSRF-Token pour fetch(). */
    public static function submittedToken(): ?string
    {
        return $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
}
