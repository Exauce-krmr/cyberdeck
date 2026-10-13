@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    @php
        $byCategory = [];
        foreach ($overview['achievements'] as $code => $achievement) {
            $byCategory[$achievement['category']][$code] = $achievement;
        }
    @endphp
    <section class="mx-auto max-w-4xl space-y-10">
        <header class="space-y-3">
            <p class="font-display text-sm font-bold uppercase tracking-[.2em] text-accent">{{ $overview['title']['label'] }}</p>
            <h1 class="glitch font-display text-4xl font-bold sm:text-5xl">{{ $user['username'] }}</h1>
            <p class="text-lg text-muted">
                <strong class="text-ice">{{ $overview['count'] }}</strong> succès sur {{ $overview['total'] }} ·
                <strong class="text-ice">{{ $owned }}</strong> cartes sur {{ $cardCount }}
            </p>
            <p class="text-sm text-muted">Membre depuis le {{ date('d.m.Y', strtotime($user['created_at'])) }}</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="panel p-4 text-center"><p class="font-display text-3xl font-bold text-ice">{{ $stats['games'] }}</p><p class="text-sm text-muted">partie(s)</p></div>
            <div class="panel p-4 text-center"><p class="font-display text-3xl font-bold text-you">{{ $stats['wins'] }}</p><p class="text-sm text-muted">victoire(s)</p></div>
            <div class="panel p-4 text-center"><p class="font-display text-3xl font-bold text-accent">{{ $stats['rate'] }} %</p><p class="text-sm text-muted">de réussite</p></div>
        </div>

        <div class="panel space-y-3 p-5">
            <h2 class="font-display text-xl font-bold">Par difficulté</h2>
            <dl class="grid gap-3 sm:grid-cols-3">
                @foreach ($difficulties as $key => $difficulty)
                    @php $d = $stats['by_difficulty'][$key] ?? ['games' => 0, 'wins' => 0]; @endphp
                    <div>
                        <dt class="text-sm text-muted">{{ $difficulty['label'] }}</dt>
                        <dd class="font-display font-bold">{{ $d['wins'] }} <span class="text-sm font-normal text-muted">victoire(s) sur {{ $d['games'] }}</span></dd>
                    </div>
                @endforeach
            </dl>
        </div>

        @php
            $titleGroups = ['count' => 'Selon tes succès', 'category' => 'Selon une catégorie', 'rank' => 'Selon le classement'];
        @endphp
        <form method="post" action="/profile/title" class="panel space-y-5 p-5" x-data="{ current: '{{ $overview['title']['code'] }}', selected: '{{ $overview['title']['code'] }}' }">
            <input type="hidden" name="_csrf" value="{{ $csrf }}">
            <div>
                <h2 class="font-display text-xl font-bold">Ton titre</h2>
                <p class="text-sm text-muted">Il s'affiche à côté de ton pseudo dans le classement. Les titres du classement se perdent si quelqu'un te dépasse.</p>
            </div>
            @foreach ($titleGroups as $type => $groupLabel)
                <fieldset class="space-y-2">
                    <legend class="text-sm font-semibold uppercase tracking-widest text-muted">{{ $groupLabel }}</legend>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($overview['titles'] as $code => $title)
                            @if ($title['type'] === $type)
                                <label class="title-choice {{ $title['available'] ? 'cursor-pointer' : 'is-locked cursor-not-allowed opacity-50' }}">
                                    <input type="radio" name="title" value="{{ $code }}" x-model="selected" class="sr-only" {{ $title['available'] ? '' : 'disabled' }}>
                                    <span class="flex items-center justify-between gap-2 font-display font-bold">
                                        <span>{{ $title['label'] }}</span>
                                        @if (!$title['available'])
                                            <svg class="title-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-label="Pas encore débloqué" role="img"><rect x="5" y="11" width="14" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                                        @endif
                                    </span>
                                    <span class="block text-sm text-muted">{{ $title['requirement'] }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <button type="submit" class="btn" disabled :disabled="selected === current">Enregistrer le titre</button>
        </form>

        @foreach ($categories as $key => $label)
            <section class="space-y-4">
                <h2 class="font-display text-2xl font-bold">{{ $label }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ($byCategory[$key] ?? [] as $code => $achievement)
                        <li class="panel flex items-center gap-4 p-4 {{ $achievement['unlocked_at'] ? '!border-accent' : 'opacity-60' }}">
                            <span class="grid size-12 shrink-0 place-items-center border-[3px] font-display text-xl font-bold {{ $achievement['unlocked_at'] ? 'border-accent text-accent' : 'border-line text-muted' }}" aria-hidden="true">{{ $achievement['unlocked_at'] ? '✓' : '?' }}</span>
                            <div class="min-w-0 flex-1">
                                {{-- La date (ou « Verrouillé ») est à droite du titre : aucune ligne vide, toutes les tuiles ont la même structure. --}}
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="font-display font-bold">{{ $achievement['label'] }}</p>
                                    <span class="shrink-0 text-xs font-semibold {{ $achievement['unlocked_at'] ? 'text-accent' : 'text-muted' }}">
                                        {{ $achievement['unlocked_at'] ? date('d.m.Y', strtotime($achievement['unlocked_at'])) : 'Verrouillé' }}
                                    </span>
                                </div>
                                <p class="text-sm text-muted">{{ $achievement['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </section>
@endsection
