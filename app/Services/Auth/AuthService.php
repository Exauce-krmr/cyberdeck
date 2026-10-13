<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Session;
use App\Models\BoosterModel;
use App\Models\CardModel;
use App\Models\CollectionModel;
use App\Models\UserModel;
use App\Services\Boosters\Boosters;
use DomainException;

final class AuthService
{
    public function __construct(
        private UserModel $users,
        private CollectionModel $collection,
        private CardModel $cards,
        private int $starterCards,
        private ?BoosterModel $stock = null,
        /** @var array<string, int> boosters offerts à l'inscription : difficulté => nombre */
        private array $welcomeBoosters = [],
    ) {
    }

    /** @return array<string, string> erreurs par champ ; vide si le compte est créé et connecté */
    public function register(string $username, string $password): array
    {
        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $username)) {
            $errors['username'] = '3 à 30 caractères : lettres, chiffres, _ ou -.';
        } elseif ($this->users->findByUsername($username) !== null) {
            $errors['username'] = 'Ce pseudo est déjà pris.';
        }
        // bcrypt ignore tout ce qui dépasse 72 octets, on refuse donc plus long.
        if (mb_strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'Entre 8 et 72 caractères.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $userId = $this->users->create($username, password_hash($password, PASSWORD_DEFAULT));
        foreach ($this->starterIds() as $cardId) {
            $this->collection->add($userId, $cardId);
        }
        foreach ($this->welcomeBoosters as $difficulty => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->stock?->add($userId, Boosters::STANDARD, $difficulty);
            }
        }
        $this->signIn($userId, $username);

        return [];
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->users->findByUsername($username);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        $this->signIn((int) $user['id'], $user['username']);

        return true;
    }

    public function logout(): void
    {
        Session::clear();
    }

    public function userId(): ?int
    {
        $id = Session::get('user_id');

        return is_int($id) ? $id : null;
    }

    /** @throws DomainException si personne n'est connecté (les routes protégées l'empêchent normalement) */
    public function requireUserId(): int
    {
        return $this->userId() ?? throw new DomainException('Connexion requise.');
    }

    /** Pseudo gardé en session : évite une requête SQL à chaque page pour l'afficher dans le menu. */
    public function username(): ?string
    {
        $name = Session::get('username');

        return is_string($name) ? $name : null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $this->userId();

        return $id === null ? null : $this->users->find($id);
    }

    private function signIn(int $userId, string $username): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('username', $username);
    }

    /** @return int[] cartes communes tirées au hasard pour démarrer */
    private function starterIds(): array
    {
        $ids = $this->cards->idsByRarity('common');
        shuffle($ids);

        return array_slice($ids, 0, $this->starterCards);
    }
}
