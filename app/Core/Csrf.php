<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('csrf');
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf', $token);
        }

        return $token;
    }

    public static function isValid(mixed $given): bool
    {
        return is_string($given) && hash_equals(self::token(), $given);
    }
}
