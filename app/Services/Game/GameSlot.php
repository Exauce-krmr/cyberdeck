<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Core\Session;

/** L'emplacement de la partie en cours dans la session : la seule classe qui connaît la clé et le format stocké. */
final class GameSlot
{
    private const SESSION_KEY = 'game';

    /** @return array<string, mixed>|null l'état de la partie de CE joueur, ou null */
    public function load(int $userId): ?array
    {
        $state = Session::get(self::SESSION_KEY);

        return is_array($state) && $state['user_id'] === $userId ? $state : null;
    }

    /** @param array<string, mixed> $state */
    public function save(int $userId, array $state): void
    {
        $state['user_id'] = $userId;
        Session::set(self::SESSION_KEY, $state);
    }

    public function clear(): void
    {
        Session::set(self::SESSION_KEY, null);
    }
}
