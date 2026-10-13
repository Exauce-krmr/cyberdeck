@extends('layouts.app')

@section('title', 'Classement')

@section('content')
    <section class="mx-auto max-w-3xl space-y-8">
        <header class="space-y-2">
            <h1 class="glitch font-display text-4xl font-bold sm:text-5xl">Classement</h1>
            <p class="text-lg text-muted">Classé par victoires. Ouvre un joueur pour voir le détail par difficulté.</p>
        </header>

        @if (count($rows) === 0)
            <div class="panel space-y-4 p-6">
                <p class="text-lg">Personne n'a encore terminé de partie.</p>
                <a href="/game" class="btn">Lancer la première</a>
            </div>
        @else
            <ol class="space-y-3">
                @foreach ($rows as $i => $row)
                    <li>
                        <details class="panel group {{ $row['username'] === $username ? '!border-you' : '' }}">
                            <summary class="flex cursor-pointer list-none items-center gap-4 px-4 py-3">
                                <span class="w-10 text-center font-display text-3xl font-bold {{ $i === 0 ? 'text-accent' : ($i < 3 ? 'text-you' : 'text-muted') }}">{{ $i + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate"><span class="text-lg font-semibold">{{ $row['username'] }}</span> <span class="ml-2 text-xs font-bold uppercase tracking-widest text-accent">{{ $row['title_label'] }}</span></span>
                                <span class="hidden text-muted sm:inline">{{ $row['games'] }} partie(s)</span>
                                <span class="text-right font-display text-2xl font-bold text-accent">{{ $row['wins'] }}<span class="ml-1 text-sm text-muted">victoire(s)</span></span>
                                <span class="text-muted transition-transform group-open:rotate-180" aria-hidden="true">▾</span>
                            </summary>
                            <dl class="grid gap-2 border-t-[3px] border-line px-4 py-3 sm:grid-cols-3">
                                @foreach ($difficulties as $key => $difficulty)
                                    @php $d = $row['by_difficulty'][$key] ?? ['games' => 0, 'wins' => 0]; @endphp
                                    <div>
                                        <dt class="text-sm text-muted">{{ $difficulty['label'] }}</dt>
                                        <dd class="font-display font-bold">{{ $d['wins'] }} <span class="text-sm font-normal text-muted">victoire(s) sur {{ $d['games'] }}</span></dd>
                                    </div>
                                @endforeach
                                <div class="sm:col-span-3 text-sm text-muted">Réussite globale : <strong class="text-ice">{{ $row['rate'] }} %</strong></div>
                            </dl>
                        </details>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
