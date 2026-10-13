@extends('layouts.app')

@section('title', 'Boosters')

@section('content')
    @php
        // Tout ce dont l'interface a besoin, lu depuis config/game.php : rien n'est écrit en dur dans la page ou le script.
        $meta = [
            'labels' => array_map(fn (array $d): string => $d['label'], $difficulties),
            'contents' => array_map(fn (array $b): array => ['cards' => $b['cards'], 'legendary' => $b['rarities'] === null || in_array('legendary', $b['rarities'], true), 'boosted' => ($b['boost'] ?? []) !== [] && max($b['boost']) > 1], $content),
            'chance' => $shiny['chance'],
            'cost' => $shiny['cost'],
        ];
    @endphp
    <section class="space-y-8" x-data="boosters" data-overview='{{ json_encode($overview) }}' data-meta='{{ json_encode($meta) }}'>
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-2">
                <h1 class="glitch font-display text-4xl font-bold sm:text-5xl">Boosters</h1>
                <p class="text-lg text-muted" x-show="!finished">Chaque victoire rapporte un booster : plus la difficulté est haute, plus il est généreux.</p>
            </div>
            <div class="shiny-wallet panel flex flex-wrap items-center gap-4 p-4" :data-locked="!unlocked" x-show="!finished">
                <div class="space-y-2">
                    <p class="font-display text-xs uppercase tracking-widest text-muted">Pièces shiny</p>
                    {{-- Une pièce par point nécessaire à l'achat : pleine quand elle est gagnée, creuse sinon --}}
                    <div class="coin-row" role="img" :aria-label="points + ' pièces shiny sur ' + meta.cost" data-help-title="Pièces shiny" :data-help="'Tu en gagnes quand un booster ne te donne rien de nouveau. Elles s\'échangent contre un booster shiny (' + meta.cost + ' pièces), mais seulement quand tu as toutes les cartes.'">
                        <template x-for="n in meta.cost" :key="n"><span class="coin coin-sm" :class="n > points && 'is-empty'"><i>✦</i></span></template>
                        <span class="coin-extra" x-show="points > meta.cost" x-text="'+' + (points - meta.cost)"></span>
                    </div>
                </div>
                <button type="button" class="btn self-end !min-h-[2.4rem]" @click="buyShiny()" :disabled="!unlocked || points < meta.cost || busy">Acheter un booster shiny</button>
                <p class="wallet-lock basis-full" x-show="!unlocked">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="5" y="11" width="14" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    Verrouillées : elles se dépensent une fois les 60 cartes obtenues.
                </p>
            </div>
        </header>

        <p class="panel border-accent p-3 text-accent" x-show="notice" x-text="notice" role="status"></p>
        <p class="panel border-danger p-3 text-danger" x-show="error" x-text="error" role="alert"></p>

        <div class="pack-grid" x-show="stock.length">
            <template x-for="pack in stock" :key="pack.id">
                <article class="pack-tile">
                    <div class="pack-stage" :data-tone="tone(pack)">
                        @include('boosters.pack', ['pack' => 'pack'])
                    </div>
                    <button type="button" class="btn w-full" @click="open(pack)" :disabled="busy">Ouvrir</button>
                </article>
            </template>
        </div>

        {{-- Tout est obtenu (cartes et skins shiny) : plus rien à gagner --}}
        <div class="panel space-y-2 p-6 text-center" x-show="finished">
            <p class="font-display text-2xl font-bold">Plus de booster</p>
            <p class="text-muted">Vous avez toutes les cartes du jeu, skins shiny compris. Le titre « Shiny » est à toi : choisis-le sur ton profil.</p>
            <a href="/collection" class="btn mt-2 inline-flex">Voir ma collection</a>
        </div>

        <div class="panel space-y-2 p-6 text-center" x-show="!stock.length && !finished">
            <p class="font-display text-2xl font-bold">Aucun booster en stock</p>
            <p class="text-muted">Gagne une partie pour en obtenir un.</p>
            <a href="/game" class="btn mt-2 inline-flex">Jouer</a>
        </div>

        {{-- Cérémonie : on contemple le paquet, un clic le déchire, puis chaque carte se retourne quand on la touche (suspense) --}}
        <div class="opening" x-show="opening" x-cloak x-transition.opacity :data-tone="opening && opening.tone" :data-dark="opening && opening.dark"
             @keydown.escape.window="close()">
            <div class="opening-bg" aria-hidden="true"></div>
            <div class="rays" aria-hidden="true" :data-on="opening && opening.rays" :data-shiny="opening && opening.shiny"></div>
            <template x-if="opening">
                <div class="opening-inner">
                    <div class="opening-intro" x-show="opening.phase === 'present'">
                    </div>
                    <div class="opening-pack-wrap" x-ref="pack" :data-phase="opening.phase" :data-glint="opening.glint" x-show="['present', 'charge', 'tear'].includes(opening.phase)"
                         :role="opening.phase === 'present' ? 'button' : null" :tabindex="opening.phase === 'present' ? 0 : null" aria-label="Ouvrir le paquet"
                         @click="launch()" @keydown.enter.prevent="launch()" @keydown.space.prevent="launch()">
                        <span class="energy" aria-hidden="true"></span><span class="energy" aria-hidden="true"></span><span class="energy" aria-hidden="true"></span>
                        <span class="glint" aria-hidden="true" style="--x: 8%; --y: 12%; --d: 0s"></span><span class="glint" aria-hidden="true" style="--x: 90%; --y: 22%; --d: .35s"></span><span class="glint" aria-hidden="true" style="--x: 18%; --y: 70%; --d: .7s"></span><span class="glint" aria-hidden="true" style="--x: 82%; --y: 82%; --d: .15s"></span><span class="glint" aria-hidden="true" style="--x: 50%; --y: 4%; --d: .5s"></span><span class="glint" aria-hidden="true" style="--x: 60%; --y: 94%; --d: .85s"></span>
                        <div class="pack-tilt" data-tilt="20">
                            <div class="pack-half pack-half-top" x-ref="top">@include('boosters.pack', ['pack' => 'opening.pack'])</div>
                            <div class="pack-half pack-half-bottom" x-ref="bottom">@include('boosters.pack', ['pack' => 'opening.pack'])</div>
                        </div>
                        <span class="beam" x-ref="beam" aria-hidden="true"></span>
                    </div>
                    <div class="opening-cta" x-show="opening.phase === 'present'">
                        <p class="opening-prompt">Touche le paquet pour l'ouvrir</p>
                        <button type="button" class="btn btn-ghost" @click.stop="close()">Plus tard</button>
                    </div>

                    <div class="opening-result" :data-arriving="opening.arriving" x-show="opening.phase === 'reveal' || opening.phase === 'done'">
                        {{-- Titre et pied de page gardent leur place : rien ne bouge quand ils apparaissent, les cartes restent immobiles --}}
                        <p class="opening-title" x-ref="title" :class="{ 'is-idle': !opening.title }" x-text="opening.title || '\u00a0'"></p>
                        <div class="opening-cards" x-ref="stage">
                            <template x-for="(item, index) in opening.items" :key="index">
                                <div class="reveal-slot" :data-aura="item.aura" :data-rarity="item.rarity" :data-flipped="item.flipped" :data-shiny="item.shiny"
                                     :data-inspect="opening.phase === 'done' && item.flipped ? '' : null" :role="opening.phase === 'done' && item.flipped ? 'button' : null"
                                     :tabindex="opening.phase === 'done' && item.flipped ? 0 : null" :aria-label="opening.phase === 'done' && item.flipped ? 'Inspecter la carte' : null">
                                    <div class="reveal-back" x-show="!item.flipped" role="button" tabindex="0" aria-label="Retourner la carte" data-tilt="16"
                                         :data-teasing="item.teasing" @click.stop="pick(index)" @keydown.enter.prevent="pick(index)" @keydown.space.prevent="pick(index)">
                                        {{-- Dos de carte : cadre double biseauté, trame, emblème et reflet qui suit le pointeur --}}
                                        <span class="back-face" aria-hidden="true">
                                            <span class="back-weave"></span>
                                            <span class="back-ring"></span>
                                            <span class="back-emblem"></span>
                                            <span class="back-glare"></span>
                                        </span>
                                    </div>
                                    <div class="card-slot" x-show="item.flipped" x-html="item.html"></div>
                                </div>
                            </template>
                        </div>
                        <div class="opening-footer">
                            <div class="opening-coins" :data-locked="!unlocked" x-show="opening.coins > 0" aria-hidden="true">
                                <template x-for="n in opening.coins" :key="n"><span class="coin"><i>✦</i></span></template>
                            </div>
                            <p class="coins-text" :data-locked="!unlocked" x-show="opening.coins > 0"><strong x-text="coinsCaption()"></strong><span x-text="coinsHelp()"></span></p>
                            <p class="opening-note" x-show="opening.note" x-text="opening.note"></p>
                            <p class="opening-hint" x-show="opening.phase === 'reveal' && !teasing && opening.items.some((item) => !item.flipped)">Touche une carte pour la retourner</p>
                            <p class="opening-hint" x-show="opening.phase === 'done' && opening.items.some((item) => item.flipped)">Touche une carte pour l'observer sous tous les angles</p>
                            <button type="button" class="btn" x-show="opening.phase === 'done'" @click.stop="close()">Continuer</button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>
@endsection
