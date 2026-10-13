@extends('layouts.app')

@section('title', 'Collection')

@section('content')
    @php
        $totalByRarity = array_count_values(array_column($cards, 'rarity'));
        $ownedByRarity = [];
        foreach ($owned as $id => $unused) {
            $rarityKey = $cards[$id]['rarity'];
            $ownedByRarity[$rarityKey] = ($ownedByRarity[$rarityKey] ?? 0) + 1;
        }
    @endphp
    <section class="space-y-8" x-data="{ rarity: 'all' }">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-2">
                <h1 class="glitch font-display text-4xl font-bold sm:text-5xl">Ma collection</h1>
                <p class="text-lg text-muted"><strong class="text-ice">{{ count($owned) }}</strong> cartes sur {{ count($cards) }}</p>
            </div>
            <p class="text-muted">
                {{ $stats['games'] }} partie(s) · {{ $stats['wins'] }} victoire(s) · <strong class="text-accent">{{ $stats['rate'] }} % de réussite</strong>
            </p>
        </header>

        <div class="flex flex-wrap gap-3" role="group" aria-label="Filtrer par rareté">
            <button type="button" class="btn btn-ghost !min-h-11" :class="rarity === 'all' && '!border-you !text-you'" @click="rarity = 'all'">Toutes</button>
            @foreach ($rarities as $key => $rarity)
                <button type="button" class="btn btn-ghost !min-h-11" :class="rarity === '{{ $key }}' && '!border-you !text-you'" @click="rarity = '{{ $key }}'">
                    {{ $rarity['label'] }} <span class="text-muted">{{ $ownedByRarity[$key] ?? 0 }}/{{ $totalByRarity[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="collection-grid grid gap-8" style="grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr))">
            @foreach ($cards as $id => $card)
              <div class="mx-auto w-full max-w-[19.5rem]" x-show="rarity === 'all' || rarity === '{{ $card['rarity'] }}'">
                <div class="card-slot mx-auto" @if (isset($owned[$id])) data-inspect role="button" tabindex="0" aria-label="Inspecter {{ $card['name'] }}" title="Cliquer pour inspecter" @endif>
                    @if (isset($owned[$id]))
                        @include('partials.card', ['card' => $card, 'shiny' => isset($shinyOn[$id]), 'shinyReady' => isset($shinyUnlocked[$id])])
                    @else
                        @include('partials.card-back', ['caption' => 'À débloquer', 'rarity' => $card['rarity']])
                    @endif
                </div>
                @if (isset($shinyUnlocked[$id]))
                    <button type="button" class="shiny-toggle" aria-pressed="{{ isset($shinyOn[$id]) ? 'true' : 'false' }}" data-shiny-toggle="{{ $id }}">✦ Skin shiny</button>
                @endif
              </div>
            @endforeach
        </div>
    </section>
@endsection
