@extends('layouts.app')

@section('title', 'Jouer')

@section('content')
    <section x-data="game" data-deck-size="{{ $deckSize }}" data-deck-limits="{{ json_encode($deckLimits) }}" x-cloak :data-phase="phase" :data-tension="tension" :data-mood="mood" :data-final="limitEnd" :data-over="over" x-effect="document.body.classList.toggle('is-immersive', immersive)" @keydown.window="onKey($event)" @click="onCardClick($event)" class="space-y-6">
        <div class="tension-veil" aria-hidden="true"></div>
        <div class="mood" aria-hidden="true"><i class="m-you"></i><i class="m-foe"></i><i class="m-win"></i><i class="m-lose"></i></div>

        <p x-show="error" x-text="error" class="panel border-foe px-4 py-3 font-semibold" role="alert"></p>

        {{-- 1. Pas de partie : compose ton deck et choisis ton adversaire --}}
        <div x-show="loaded && !game" class="space-y-8">
            <header class="space-y-3">
                <h1 class="glitch font-display text-4xl font-bold leading-tight sm:text-5xl">Prépare ta partie</h1>
                <p class="max-w-prose text-lg text-muted">
                    Compose un deck de {{ $deckSize }} cartes dans ta collection
                    @if ($deckLimits)
                        (au plus {{ implode(', ', array_map(fn (string $rarity, int $max): string => $max.' '.mb_strtolower($rarities[$rarity]['label']).'s', array_keys($deckLimits), $deckLimits)) }})
                    @endif. L'ordinateur reçoit les mêmes raretés que toi : à toi de repérer tes forces.
                </p>
            </header>

            <div class="grid items-start gap-8 lg:grid-cols-[22rem_1fr]">
                <aside class="panel space-y-6 p-5 lg:sticky lg:top-28 z-20">
                    <fieldset class="space-y-3">
                        <legend class="font-display text-lg font-bold">Adversaire</legend>
                        @foreach ($difficulties as $key => $difficulty)
                            <button type="button" @click="difficulty = '{{ $key }}'" :aria-pressed="difficulty === '{{ $key }}'" class="choice">
                                <span class="flex shrink-0 gap-1" aria-hidden="true">
                                    @for ($i = 1; $i <= 3; $i++)
                                        <span class="h-5 w-2 {{ $i <= $difficulty['points'] ? 'bg-accent' : 'bg-line' }}"></span>
                                    @endfor
                                </span>
                                <span class="flex-1">
                                    <span class="block font-display font-bold">{{ $difficulty['label'] }}</span>
                                    <span class="block text-sm text-muted">{{ $difficulty['description'] }}</span>
                                </span>
                            </button>
                        @endforeach
                    </fieldset>

                    <div class="space-y-3">
                        <div class="flex items-baseline justify-between">
                            <h2 class="font-display text-lg font-bold">Ton deck</h2>
                            <div class="flex items-baseline gap-3">
                                <button type="button" class="text-sm text-muted underline underline-offset-4 hover:text-foe disabled:no-underline disabled:opacity-40" @click="clearDeck()" :disabled="deck.length === 0">Tout désélectionner</button>
                                <p class="font-display"><span class="text-2xl font-bold" :class="deck.length === {{ $deckSize }} ? 'text-you' : 'text-accent'" x-text="deck.length"></span>/{{ $deckSize }}</p>
                            </div>
                        </div>
                        @if ($deckLimits)
                            {{-- Limites de composition : bien visibles, et le curseur devient « ? » avec une bulle qui explique la règle (data-help, voir fx/cursor.js) --}}
                            <div class="deck-limits">
                                @foreach ($deckLimits as $rarity => $max)
                                    <div class="deck-limit rarity-{{ $rarity }}" tabindex="0" role="note" :data-full="rarityCounts['{{ $rarity }}'] >= {{ $max }}"
                                         data-help-tone="warn" data-help-title="Limite de deck · {{ $rarities[$rarity]['label'] }}s"
                                         data-help="Tu peux mettre {{ $max }} carte{{ $max > 1 ? 's' : '' }} {{ mb_strtolower($rarities[$rarity]['label']) }}{{ $max > 1 ? 's' : '' }} au maximum dans ton deck, pour éviter les decks trop puissants. Une fois la limite atteinte, les autres cartes {{ mb_strtolower($rarities[$rarity]['label']) }}s se bloquent."
                                         aria-label="{{ $rarities[$rarity]['label'] }}s : {{ $max }} au maximum dans un deck">
                                        <span class="deck-limit-name">{{ $rarities[$rarity]['label'] }}s</span>
                                        <span class="deck-limit-count"><b x-text="rarityCounts['{{ $rarity }}']"></b>/{{ $max }}</span>
                                        <span class="deck-limit-pips" aria-hidden="true">
                                            @for ($i = 1; $i <= $max; $i++)
                                                <i :class="rarityCounts['{{ $rarity }}'] >= {{ $i }} && 'is-on'"></i>
                                            @endfor
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <ul class="space-y-1">
                            @foreach (array_keys($attributes) as $index => $key)
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="w-24 text-muted">{{ $attributes[$key] }}</span>
                                    <span class="h-2 flex-1 bg-line"><span class="block h-full bg-you transition-all duration-300" :style="`width: ${averages[{{ $index }}]}%`"></span></span>
                                    <span class="w-8 text-right font-display font-bold" x-text="averages[{{ $index }}]"></span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-sm text-muted">Moyenne de ton deck : vise ce que ton deck fait de mieux quand tu mènes.</p>
                        {{-- Deck automatique : un groupe avec le tirage au hasard et le choix d'une stratégie (popover) --}}
                        <div class="auto-group relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                            <p class="auto-group-title">Composer automatiquement</p>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" class="btn btn-ghost" @click="autoDeck('random')" title="10 cartes tirées au sort">Au hasard</button>
                                <button type="button" class="btn btn-ghost" @click="open = !open" :aria-expanded="open" aria-haspopup="true" :class="open && '!border-you !text-you'">
                                    Stratégique <span aria-hidden="true" x-text="open ? '◂' : '▸'"></span>
                                </button>
                            </div>
                            <div x-show="open" x-transition.opacity.duration.150ms class="popover" role="menu" aria-label="Stratégie du deck">
                                <p class="popover-title">Stratégie du deck</p>
                                <button type="button" role="menuitem" class="choice" @click="autoDeck('balanced'); open = false">
                                    <span class="glyph" aria-hidden="true"><i style="height:70%"></i><i style="height:70%"></i><i style="height:70%"></i><i style="height:70%"></i><i style="height:70%"></i></span>
                                    <span class="flex-1"><span class="block font-display font-bold">Équilibré</span><span class="block text-sm text-muted">Un avantage partout : relève toujours ton attribut le plus faible.</span></span>
                                </button>
                                <button type="button" role="menuitem" class="choice" @click="autoDeck('strongest'); open = false">
                                    <span class="glyph" aria-hidden="true"><i style="height:100%"></i><i style="height:85%"></i><i style="height:100%"></i><i style="height:90%"></i><i style="height:95%"></i></span>
                                    <span class="flex-1"><span class="block font-display font-bold">Les plus fortes</span><span class="block text-sm text-muted">Les cartes qui ont le plus de points au total.</span></span>
                                </button>
                                <div class="choice !items-start">
                                    <span class="glyph" aria-hidden="true"><i style="height:30%"></i><i style="height:100%"></i><i style="height:30%"></i><i style="height:30%"></i><i style="height:30%"></i></span>
                                    <span class="flex-1">
                                        <span class="block font-display font-bold">Spécialiste</span>
                                        <span class="block text-sm text-muted">Mise tout sur un attribut, pour les manches que tu mènes.</span>
                                        <span class="popover-chips">
                                            @foreach (array_keys($attributes) as $index => $key)
                                                <button type="button" role="menuitem" @click="autoDeck('specialist', {{ $index }}); open = false">{{ $attributes[$key] }}</button>
                                            @endforeach
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn w-full" :disabled="deck.length !== {{ $deckSize }} || busy" @click="startGame()">Lancer la partie</button>
                </aside>

                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr))">
                    @foreach ($ownedCards as $card)
                        <button type="button" class="deck-card" data-card-id="{{ $card['id'] }}" data-rarity="{{ $card['rarity'] }}" :class="{ 'is-blocked': isBlocked({{ $card['id'] }}) }" :aria-disabled="isBlocked({{ $card['id'] }})"
                                data-values="{{ implode(',', array_map(fn ($key) => $card[$key], array_keys($attributes))) }}"
                                @click="toggleCard({{ $card['id'] }})" :aria-pressed="deck.includes({{ $card['id'] }})">
                            @include('partials.card-mini', ['card' => $card])
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="sticky bottom-4 z-10 lg:hidden">
                <button type="button" class="btn w-full" :disabled="deck.length !== {{ $deckSize }} || busy" @click="startGame()">
                    Lancer la partie (<span x-text="deck.length"></span>/{{ $deckSize }})
                </button>
            </div>
        </div>

        {{-- 2. Manche en cours, ou manche qui vient d'être jouée (même quand elle termine la partie) --}}
        <template x-if="game && (view.status === 'playing' || result)">
            <div class="game-col space-y-4 pb-40 sm:space-y-6">
                {{-- Barre de jeu fixe : remplace le header pendant la partie (☰ = menu de navigation) --}}
                <div class="hud">
                    <button type="button" class="hud-menu" @click="document.body.classList.toggle('menu-open')" aria-label="Menu de navigation">☰</button>
                    <p class="hud-score"><span class="text-you" x-text="shown.player"></span><small>toi</small></p>
                    <div class="hud-mid">
                        <p class="hud-round" :data-endgame="endgame">
                            Manche <span x-text="game.round + (result ? 0 : 1)"></span>/<span x-text="game.max_rounds"></span>
                            <span x-show="endgame !== 'none'" class="end-pill" x-text="endgame === 'last' ? 'dernière' : roundsLeft + ' restantes'"></span>
                            <span x-show="game.pot > 0 && !over" class="pot-pill">cagnotte <b x-text="game.pot"></b></span>
                        </p>
                        <div class="tug" role="img" :aria-label="`Toi ${game.player_count} cartes, ordinateur ${game.ai_count} cartes`">
                            <span class="bg-you" :style="`width: ${share('player_count')}%`"></span>
                            <span class="bg-accent" :style="`width: ${share('pot')}%`"></span>
                            <span class="flex-1 bg-foe"></span>
                        </div>
                    </div>
                    <p class="hud-score"><span class="text-foe" x-text="shown.ai"></span><small>ordinateur</small></p>
                    <button type="button" class="btn btn-danger hud-quit" @click="abandon()" :aria-pressed="confirmAbandon" :disabled="!can('abandon')"
                            x-text="confirmAbandon ? 'Confirmer : défaite' : 'Abandonner'"></button>
                </div>

                <div class="banner" :data-tone="banner.tone" aria-live="polite"
                     x-effect="banner.title, banner.detail; $el.classList.remove('appear'); void $el.offsetWidth; $el.classList.add('appear')">
                    <p class="banner-title" x-text="banner.title"></p>
                    <p class="banner-detail" x-text="banner.detail"></p>
                </div>

                {{-- Moment clé (changement de tête, danger…) : bandeau en travers de l'écran, façon annonce, qui entre vite puis repart seul --}}
                <div class="event-slot" :data-open="callout ? 'true' : 'false'">
                    <div class="event-bar" :data-tone="lastCallout && lastCallout.tone" role="status">
                        <p class="event-title" x-text="lastCallout && lastCallout.title"></p>
                        <p class="event-detail" x-text="lastCallout && lastCallout.detail"></p>
                    </div>
                </div>

                <div class="abilities" role="group" aria-label="Capacités">
                    @foreach ($abilities as $key => $ability)
                        <button type="button" class="ability" @click="useAbility('{{ $key }}')" :disabled="!canUseAbility('{{ $key }}')"
                                :aria-pressed="isArmed('{{ $key }}')" data-help-tone="warn" data-help-title="{{ $ability['label'] }}" data-help="{{ $ability['description'] }} Utilisable {{ $ability['uses'] }} fois par partie.">
                            <span class="ability-name">{{ $ability['label'] }}</span>
                            <span class="ability-left" x-text="game.abilities.{{ $key }}"></span>
                            <span class="ability-desc">{{ $ability['description'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div x-ref="arena" class="arena" :class="{ 'is-reveal': result, 'is-choose': !result, 'is-tie': result && result.outcome === 'tie' }">
                    <div class="arena-side arena-player">
                        <div class="you-tag" :data-active="phase === 'choosing' || null">
                            <p class="foe-name"><span>Toi</span><em x-text="result ? 'carte jouée' : 'ta main'"></em></p>
                        </div>
                        <div class="hand">
                            <template x-if="result"><div class="card-slot" x-html="result.player_html"></div></template>
                            <template x-for="(html, i) in (result ? [] : game.hand_html)" :key="i">
                                <div class="card-slot hand-slot" :data-hand="i" :class="{ 'is-answerable': phase === 'answering', 'is-focused': keyboardFocus && i === focusHand }" x-html="html"></div>
                            </template>
                        </div>
                        <p class="verdict" x-show="result && !holdEnd" :data-won="verdict('player').won" x-text="verdict('player').text"></p>
                    </div>

                    <div class="arena-vs" aria-hidden="true" :data-vs="result ? result.outcome : phase"><span x-text="result && result.outcome === 'tie' ? '=' : 'VS'"></span></div>

                    {{-- Carte adverse : cachée jusqu'à la fin de la manche --}}
                    <div class="arena-side arena-ai">
                        <div class="foe-tag" :data-active="phase === 'answering' || null" :data-stress="foeStress">
                            <p class="foe-name"><span>Ordinateur</span><em x-text="game.difficulty_label"></em></p>
                            <p class="foe-line" x-text="foeSays"></p>
                        </div>
                        <div class="announce" x-show="phase === 'answering'"><span>Attribut annoncé</span><strong x-text="game.announced_label"></strong></div>
                        <div class="card-slot">
                            <template x-if="result"><div x-html="result.ai_html"></div></template>
                            <template x-if="!result"><div>@include('partials.card-back', ['caption' => 'Carte cachée'])</div></template>
                        </div>
                        <p class="verdict" x-show="result && !holdEnd" :data-won="verdict('ai').won" x-text="verdict('ai').text"></p>
                    </div>
                </div>

                {{-- Dock : réserves (ta prochaine carte, cartes vues de l'ordinateur) et passage à la manche suivante --}}
                {{-- Défaite faute de cartes : la partie enchaîne seule sur l'écran de fin, le dock n'a rien à proposer (il clignotait) --}}
                <div class="dock" x-show="!(game.status === 'lose' && game.player_count === 0)" x-init="dockSolo = view.status !== 'playing'" :class="{ 'is-leaving': view.status !== 'playing', 'is-final': dockSolo }">
                    {{-- En fin de partie les réserves glissent vers le bas (is-leaving), puis le dock se réduit au seul bouton du résultat (is-final, voir component.js) --}}
                    <div class="dock-next" x-html="game.my_pile_html"></div>
                    <div class="dock-seen" x-html="game.pile_html"></div>
                    <button x-show="phase === 'revealed'" x-ref="dockGo" type="button" @click="next()" class="btn dock-go">
                        <span x-text="view.status === 'playing' ? 'Manche suivante' : 'Voir le résultat'"></span>
                        <kbd class="hidden bg-ink px-2 py-1 text-xs text-ice md:inline">Entrée</kbd>
                    </button>
                </div>
            </div>
        </template>

        {{-- 3. Partie terminée --}}
        <template x-if="view && view.status !== 'playing' && !result">
            <div class="mx-auto grid max-w-4xl items-center gap-8 md:grid-cols-[1fr_auto]">
                <div class="space-y-6">
                    <h1 class="font-display text-5xl font-bold leading-none sm:text-6xl"
                        :class="game.status === 'win' ? 'text-you' : game.status === 'lose' ? 'text-foe' : 'text-ice'"
                        x-text="{ win: 'Victoire', lose: 'Défaite', draw: 'Égalité' }[game.status]"></h1>
                    <p class="text-lg text-muted">
                        Partie terminée en <span x-text="game.round"></span> manches :
                        <strong class="text-ice" x-text="game.player_count"></strong> cartes pour toi,
                        <strong class="text-ice" x-text="game.ai_count"></strong> pour l'ordinateur.
                    </p>
                    <template x-if="game.unlocked.achievements.length">
                        <div class="panel space-y-2 !border-accent p-4" role="status">
                            <p class="font-display text-sm font-bold uppercase tracking-[.2em] text-accent">Succès débloqué(s)</p>
                            <ul class="space-y-1">
                                <template x-for="achievement in game.unlocked.achievements" :key="achievement.code">
                                    <li><strong class="text-ice" x-text="achievement.label"></strong> <span class="text-muted" x-text="'· ' + achievement.description"></span></li>
                                </template>
                            </ul>
                            <p x-show="game.unlocked.titles.length" class="text-sm text-muted">Nouveau titre : <strong class="text-accent" x-text="game.unlocked.titles.join(', ')"></strong> (à choisir sur ton <a href="/profile" class="underline">profil</a>).</p>
                        </div>
                    </template>
                    <template x-if="game.summary">
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="panel p-3"><p class="font-display text-3xl font-bold text-you" x-text="game.summary.won"></p><p class="text-sm text-muted">manches gagnées</p></div>
                                <div class="panel p-3"><p class="font-display text-3xl font-bold text-foe" x-text="game.summary.lost"></p><p class="text-sm text-muted">perdues</p></div>
                                <div class="panel p-3"><p class="font-display text-3xl font-bold text-ice" x-text="game.summary.tied"></p><p class="text-sm text-muted">égalités</p></div>
                            </div>
                            <ul class="space-y-1 text-muted">
                                <li x-show="game.summary.best">Carte la plus efficace : <strong class="text-ice" x-text="game.summary.best?.name"></strong> (<span x-text="game.summary.best?.wins"></span> manche(s) gagnée(s))</li>
                                <li x-show="game.summary.biggest">Plus gros écart : <strong class="text-ice" x-text="game.summary.biggest?.card"></strong> en <span x-text="game.summary.biggest?.attribute_label"></span>, <strong class="text-you" x-text="'+' + game.summary.biggest?.gap"></strong> à la manche <span x-text="game.summary.biggest?.round"></span></li>
                            </ul>
                            <details class="panel group p-3">
                                <summary class="cursor-pointer font-display font-bold uppercase tracking-wider text-muted">Détail des manches</summary>
                                <ol class="mt-3 space-y-1 text-sm">
                                    <template x-for="round in game.summary.rounds" :key="round.round">
                                        <li class="flex flex-wrap items-baseline gap-x-2 border-t border-line pt-1">
                                            <span class="w-6 text-muted" x-text="round.round"></span>
                                            <span class="font-bold" :class="round.outcome === 'player' ? 'text-you' : round.outcome === 'ai' ? 'text-foe' : 'text-muted'"
                                                  x-text="{ player: 'Gagnée', ai: 'Perdue', tie: 'Égalité' }[round.outcome]"></span>
                                            <span class="text-muted" x-text="round.attribute_label + ' : ' + round.player_name + ' ' + round.player_value + ' contre ' + round.ai_name + ' ' + round.ai_value"></span>
                                        </li>
                                    </template>
                                </ol>
                            </details>
                        </div>
                    </template>
                    <p x-show="rewardText" class="font-display text-xl font-bold text-accent" x-text="rewardText"></p>

                    <div class="flex flex-wrap gap-4">
                        <button type="button" @click="game = null" class="btn">Rejouer</button>
                        <a href="/boosters" class="btn btn-ghost">Ouvrir mes boosters</a>
                    </div>
                </div>

            </div>
        </template>
    </section>
@endsection
