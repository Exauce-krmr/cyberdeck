<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, array<string, mixed>> */
    private static array $files = [];

    /** Lit une valeur avec la notation "fichier.cle.souscle", par exemple "game.deck_size". */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        self::$files[$file] ??= require BASE_PATH . "/config/{$file}.php";

        $value = self::$files[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
