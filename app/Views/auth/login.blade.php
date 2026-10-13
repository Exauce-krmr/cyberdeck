@extends('layouts.app')

@section('title', 'Connexion')

@section('content')
    <section class="mx-auto grid max-w-4xl items-center gap-10 md:grid-cols-2">
        <div class="space-y-4">
            <h1 class="glitch font-display text-4xl font-bold leading-tight sm:text-5xl">Reviens dans l'arène</h1>
            <p class="max-w-prose text-lg text-muted">Retrouve ta collection, tes cartes rares et ta place au classement.</p>
        </div>

        <form method="post" action="/login" class="panel space-y-5 p-6 sm:p-8">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">

            @if ($error)
                <p class="border-l-8 border-foe bg-foe/10 px-3 py-2 font-semibold" role="alert">{{ $error }}</p>
            @endif

            <label class="block font-semibold">Pseudo
                <input name="username" value="{{ $old }}" required autocomplete="username" autofocus class="field">
            </label>
            <label class="block font-semibold">Mot de passe
                <input type="password" name="password" required autocomplete="current-password" class="field">
            </label>

            <button class="btn w-full">Se connecter</button>
            <p class="text-muted">Pas encore de compte ? <a href="/register" class="font-semibold text-you underline">Crée le tien</a></p>
        </form>
    </section>
@endsection
