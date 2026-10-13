@extends('layouts.app')

@section('title', 'Inscription')

@section('content')
    <section class="mx-auto grid max-w-4xl items-center gap-10 md:grid-cols-2">
        <div class="space-y-4">
            <h1 class="glitch font-display text-4xl font-bold leading-tight sm:text-5xl">Reçois ton premier deck</h1>
            <p class="max-w-prose text-lg text-muted">
                12 cartes offertes à l'inscription. Chaque victoire en débloque une nouvelle, avec une chance de tomber sur une rare, une épique ou une légendaire.
            </p>
        </div>

        <form method="post" action="/register" class="panel space-y-5 p-6 sm:p-8">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">

            <label class="block font-semibold">Pseudo
                <input name="username" value="{{ $old }}" required autocomplete="username" autofocus class="field">
                @isset($errors['username'])
                    <span class="mt-2 block border-l-8 border-foe bg-foe/10 px-3 py-1" role="alert">{{ $errors['username'] }}</span>
                @endisset
            </label>
            <label class="block font-semibold">Mot de passe <span class="font-normal text-muted">(8 caractères minimum)</span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password" class="field">
                @isset($errors['password'])
                    <span class="mt-2 block border-l-8 border-foe bg-foe/10 px-3 py-1" role="alert">{{ $errors['password'] }}</span>
                @endisset
            </label>

            <button class="btn w-full">Créer mon compte</button>
            <p class="text-muted">Déjà inscrit ? <a href="/login" class="font-semibold text-you underline">Connecte-toi</a></p>
        </form>
    </section>
@endsection
