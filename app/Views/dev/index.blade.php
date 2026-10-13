@extends('layouts.app')

@section('title', 'Labo')

@section('content')
    @php
        $statuses = ['win' => 'Victoire', 'lose' => 'Défaite', 'draw' => 'Égalité'];
        // id => libellé : l'ordre va du plus utilisé (voir une fin de partie) au plus rare (comptes de test)
        $tabs = [
            'ends'     => 'Fins de partie',
            'custom'   => 'Partie sur mesure',
            'boosters' => 'Boosters et pièces',
            'cards'    => 'Collection',
            'history'  => 'Historique et succès',
            'accounts' => 'Comptes de test',
        ];
    @endphp
    <section class="space-y-6" x-data="lab" data-selectable data-tabs='{{ json_encode(array_keys($tabs)) }}'>
        <header class="space-y-2">
            <h1 class="glitch font-display text-4xl font-bold sm:text-5xl">Labo</h1>
            <p class="text-muted">Outils de test, réservés aux pseudos de <code>DEV_USERS</code> quand <code>DEV_TOOLS=1</code>. Les actions agissent sur ton compte connecté.</p>
        </header>

        @if ($flash)
            <p class="panel p-3 font-semibold {{ $flash['ok'] ? 'border-accent text-accent' : 'border-danger text-danger' }}" role="status">{{ $flash['text'] }}</p>
        @endif

        {{-- L'état du compte reste sous les yeux pendant qu'on change d'onglet --}}
        <dl class="lab-status panel" aria-label="État du compte">
            <div><dt>Collection</dt><dd>{{ $snapshot['cards'] }} / {{ $snapshot['total'] }}</dd></div>
            <div><dt>Skins shiny</dt><dd>{{ $snapshot['shinies'] }}</dd></div>
            <div><dt>Pièces shiny</dt><dd>{{ $snapshot['points'] }}</dd></div>
            <div>
                <dt>Boosters en stock</dt>
                <dd class="lab-status-stock">
                    @forelse ($snapshot['boosters'] as $label => $count)
                        <span>{{ $count }} × {{ $label }}</span>
                    @empty
                        <span class="text-muted">aucun</span>
                    @endforelse
                </dd>
            </div>
        </dl>

        <nav class="lab-tabs" role="tablist" aria-label="Outils du Labo">
            @foreach ($tabs as $id => $label)
                <button type="button" role="tab" class="lab-tab" id="tab-{{ $id }}" aria-controls="panel-{{ $id }}"
                        :aria-selected="tab === '{{ $id }}'" :data-on="tab === '{{ $id }}'" @click="show('{{ $id }}')">{{ $label }}</button>
            @endforeach
        </nav>

        {{-- ===== Fins de partie ===== --}}
        <div id="panel-ends" role="tabpanel" aria-labelledby="tab-ends" class="lab-panel" x-show="tab === 'ends'" x-cloak>
            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="scenario">
                <div class="lab-tool-head">
                    <h2>Voir une animation de fin</h2>
                    <p>Installe une partie à une manche de la fin et t'emmène sur Jouer : tu joues toi-même la dernière manche (n'importe quelle carte et attribut, l'issue est garantie) et la vraie séquence se déclenche.</p>
                </div>
                <label class="lab-field">Difficulté
                    @include('dev.difficulty')
                </label>
                <div class="lab-buttons-grid">
                    @foreach ($scenarios as $key => $label)
                        <button class="btn btn-ghost" name="scenario" value="{{ $key }}">{{ $label }}</button>
                    @endforeach
                </div>
            </form>

            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="finish_game">
                <div class="lab-tool-head">
                    <h2>Terminer sans animation</h2>
                    <p>Enregistre un résultat tout de suite par le vrai chemin de fin (score, récompense, succès), sans passer par l'écran de jeu. Si une partie est en cours, sa difficulté prime.</p>
                </div>
                <div class="lab-row">
                    <label class="lab-field">Issue
                        <select name="status" class="field">
                            @foreach ($statuses as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="lab-field">Difficulté
                        @include('dev.difficulty')
                    </label>
                    <button class="btn btn-ghost">Enregistrer</button>
                </div>
            </form>
        </div>

        {{-- ===== Partie sur mesure ===== --}}
        <div id="panel-custom" role="tabpanel" aria-labelledby="tab-custom" class="lab-panel" x-show="tab === 'custom'" x-cloak>
            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="setup_game">
                <div class="lab-tool-head">
                    <h2>Fabriquer une partie</h2>
                    <p>Pose l'état voulu, puis ouvre Jouer. La graine rend la partie reproductible (mêmes piles, mêmes choix de l'ordinateur). Une carte laissée vide est tirée au hasard.</p>
                </div>

                <fieldset class="lab-group">
                    <legend>Réglages</legend>
                    <div class="lab-row">
                        <label class="lab-field">Difficulté @include('dev.difficulty')</label>
                        <label class="lab-field">Mes cartes <input type="number" name="player_count" value="10" min="1" max="60" class="field w-24"></label>
                        <label class="lab-field">Cartes ordinateur <input type="number" name="ai_count" value="10" min="1" max="60" class="field w-24"></label>
                        <label class="lab-field">Manches jouées <input type="number" name="round" value="0" min="0" max="{{ $maxRounds - 1 }}" class="field w-24"></label>
                        <label class="lab-field">Cagnotte <input type="number" name="pot" value="0" min="0" max="10" class="field w-24"></label>
                        <label class="lab-field">Meneur
                            <select name="leader" class="field"><option value="player">Moi</option><option value="ai">Ordinateur</option></select>
                        </label>
                        <label class="lab-field">Graine <input type="number" name="seed" placeholder="aléatoire" class="field w-32"></label>
                    </div>
                </fieldset>

                <div class="lab-two">
                    <fieldset class="lab-group">
                        <legend>Ma main (dessus de pile)</legend>
                        @for ($i = 0; $i < $handSize; $i++)
                            <select name="player_top[]" class="field w-full" aria-label="Ma carte {{ $i + 1 }}">
                                <option value="">Carte {{ $i + 1 }} : au hasard</option>
                                @foreach ($cardList as $card)
                                    <option value="{{ $card['id'] }}">{{ $card['name'] }} ({{ $card['rarity'] }})</option>
                                @endforeach
                            </select>
                        @endfor
                    </fieldset>
                    <fieldset class="lab-group">
                        <legend>Main de l'ordinateur</legend>
                        @for ($i = 0; $i < $handSize; $i++)
                            <select name="ai_top[]" class="field w-full" aria-label="Carte {{ $i + 1 }} de l'ordinateur">
                                <option value="">Carte {{ $i + 1 }} : au hasard</option>
                                @foreach ($cardList as $card)
                                    <option value="{{ $card['id'] }}">{{ $card['name'] }} ({{ $card['rarity'] }})</option>
                                @endforeach
                            </select>
                        @endfor
                    </fieldset>
                </div>

                <div class="lab-buttons">
                    <button class="btn">Installer la partie</button>
                    <a href="/game" class="btn btn-ghost">Aller jouer</a>
                </div>
            </form>
        </div>

        {{-- ===== Boosters et pièces ===== --}}
        <div id="panel-boosters" role="tabpanel" aria-labelledby="tab-boosters" class="lab-panel" x-show="tab === 'boosters'" x-cloak>
            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="give_boosters">
                <div class="lab-tool-head"><h2>Donner des boosters</h2></div>
                <div class="lab-row">
                    @include('dev.target')
                    <label class="lab-field">Type
                        <select name="kind" class="field"><option value="standard">Standard</option><option value="shiny">Shiny</option></select>
                    </label>
                    <label class="lab-field">Difficulté @include('dev.difficulty')</label>
                    <label class="lab-field">Nombre <input type="number" name="count" value="1" min="1" max="20" class="field w-24"></label>
                    <button class="btn">Donner</button>
                </div>
            </form>

            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="shiny_points">
                <div class="lab-tool-head"><h2>Pièces shiny</h2></div>
                <div class="lab-row">
                    <label class="lab-field">Valeur <input type="number" name="points" value="{{ $snapshot['points'] }}" min="0" max="9999" class="field w-28"></label>
                    <button class="btn btn-ghost">Fixer</button>
                </div>
            </form>

            <div class="lab-tool">
                <div class="lab-tool-head"><h2>Stock</h2></div>
                <div class="lab-buttons">
                    @include('dev.action', ['action' => 'clear_boosters', 'label' => 'Vider le stock'])
                </div>
            </div>
        </div>

        {{-- ===== Collection ===== --}}
        <div id="panel-cards" role="tabpanel" aria-labelledby="tab-cards" class="lab-panel" x-show="tab === 'cards'" x-cloak>
            <div class="lab-tool">
                <div class="lab-tool-head"><h2>Cartes possédées</h2></div>
                <div class="lab-buttons">
                    @include('dev.action', ['action' => 'collection', 'fields' => ['mode' => 'all'], 'label' => 'Tout débloquer'])
                    @include('dev.action', ['action' => 'collection', 'fields' => ['mode' => 'none'], 'label' => 'Tout vider'])
                </div>
                <form method="post" action="/dev" class="lab-row">
                    <input type="hidden" name="_csrf" value="{{ $csrf }}">
                    <input type="hidden" name="action" value="collection">
                    <input type="hidden" name="mode" value="except">
                    <label class="lab-field">Tout sauf (cartes au hasard)
                        <input type="number" name="missing" value="2" min="1" max="59" class="field w-24">
                    </label>
                    <button class="btn btn-ghost">Appliquer</button>
                </form>
            </div>

            @php
                $byRarity = [];
                foreach ($cardList as $card) {
                    $byRarity[$card['rarity']][] = $card;
                }
            @endphp
            {{-- Sélecteur de cartes : une grille de pastilles à cocher, groupées par rareté, avec recherche (pas de liste à Ctrl-clic) --}}
            <form method="post" action="/dev" class="lab-tool lab-wide" x-data="{ q: '', count: 0 }" @change="count = $el.querySelectorAll('.pick input:checked').length">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <div class="lab-tool-head"><h2>Donner ou retirer des cartes précises</h2><p>Coche les cartes voulues, puis choisis l'action. Les comptes de test ne s'utilisent qu'en s'y connectant.</p></div>
                <div class="lab-row">
                    @include('dev.target')
                    <label class="lab-field lab-grow"><span>Rechercher une carte</span>
                        <input type="search" x-model="q" class="field" placeholder="Nom de la carte…" autocomplete="off">
                    </label>
                </div>
                <div class="pick-groups" tabindex="0" aria-label="Liste des cartes">
                    @foreach ($byRarity as $rarity => $group)
                        <fieldset class="pick-group rarity-{{ $rarity }}" data-group="{{ $rarity }}">
                            <legend>{{ $rarities[$rarity]['label'] ?? $rarity }}s</legend>
                            <div class="pick-actions">
                                <button type="button" class="pick-link" @click="$el.closest('.pick-group').querySelectorAll('.pick:not([hidden]) input').forEach((box) => box.checked = true); count = $root.querySelectorAll('.pick input:checked').length">Tout cocher</button>
                                <button type="button" class="pick-link" @click="$el.closest('.pick-group').querySelectorAll('.pick input').forEach((box) => box.checked = false); count = $root.querySelectorAll('.pick input:checked').length">Décocher</button>
                            </div>
                            <div class="pick-grid">
                                @foreach ($group as $card)
                                    <label class="pick" x-show="!q || '{{ mb_strtolower(addslashes($card['name'])) }}'.includes(q.toLowerCase())">
                                        <input type="checkbox" name="cards[]" value="{{ $card['id'] }}" class="sr-only">
                                        <span>{{ $card['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                <div class="lab-row">
                    <button class="btn" name="action" value="give_cards" :disabled="!count">Donner</button>
                    <button class="btn btn-ghost" name="action" value="remove_cards" :disabled="!count">Retirer</button>
                    <p class="text-sm text-muted" x-text="count ? count + ' carte' + (count > 1 ? 's' : '') + ' cochée' + (count > 1 ? 's' : '') : 'Aucune carte cochée'"></p>
                </div>
            </form>

            <div class="lab-tool">
                <div class="lab-tool-head"><h2>Skins shiny</h2></div>
                <div class="lab-buttons">
                    @include('dev.action', ['action' => 'shinies', 'fields' => ['mode' => 'on'], 'label' => 'Shiny sur toutes mes cartes'])
                    @include('dev.action', ['action' => 'shinies', 'fields' => ['mode' => 'off'], 'label' => 'Retirer les shiny'])
                </div>
            </div>
        </div>

        {{-- ===== Historique et succès ===== --}}
        <div id="panel-history" role="tabpanel" aria-labelledby="tab-history" class="lab-panel" x-show="tab === 'history'" x-cloak>
            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="fake_games">
                <div class="lab-tool-head"><h2>Historique factice</h2></div>
                <div class="lab-row">
                    <label class="lab-field">Victoires <input type="number" name="wins" value="3" min="0" max="100" class="field w-24"></label>
                    <label class="lab-field">Défaites <input type="number" name="losses" value="1" min="0" max="100" class="field w-24"></label>
                    <label class="lab-field">En @include('dev.difficulty')</label>
                    <button class="btn btn-ghost">Ajouter</button>
                </div>
            </form>

            <div class="lab-tool">
                <div class="lab-tool-head"><h2>Remises à zéro</h2></div>
                <div class="lab-buttons">
                    @include('dev.action', ['action' => 'clear_scores', 'label' => 'Effacer mon historique'])
                    @include('dev.action', ['action' => 'achievements', 'fields' => ['mode' => 'unlock'], 'label' => 'Débloquer tous les succès'])
                    @include('dev.action', ['action' => 'achievements', 'fields' => ['mode' => 'clear'], 'label' => 'Retirer les succès'])
                </div>
            </div>
        </div>

        {{-- ===== Comptes de test ===== --}}
        <div id="panel-accounts" role="tabpanel" aria-labelledby="tab-accounts" class="lab-panel" x-show="tab === 'accounts'" x-cloak>
            <form method="post" action="/dev" class="lab-tool">
                <input type="hidden" name="_csrf" value="{{ $csrf }}">
                <input type="hidden" name="action" value="create_account">
                <div class="lab-tool-head">
                    <h2>Créer un compte</h2>
                    <p>Pseudo en <code>test_…</code>, mot de passe <code>{{ $testPassword }}</code>. Les comptes apparaissent au classement tant qu'ils ne sont pas supprimés.</p>
                </div>
                <div class="lab-row">
                    <label class="lab-field"><span>Nom <small class="font-normal">(après <code>test_</code>)</small></span>
                        <input name="name" required maxlength="20" pattern="[A-Za-z0-9_-]+" class="field w-40" placeholder="alice">
                    </label>
                    <label class="lab-field">Profil
                        <select name="preset" class="field">
                            @foreach ($presets as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn">Créer</button>
                </div>
            </form>

            <div class="lab-tool">
                <div class="lab-tool-head"><h2>Comptes existants</h2><p>Seuls les comptes <code>test_</code> peuvent être supprimés ici.</p></div>
                @if ($testAccounts)
                    <ul class="lab-accounts">
                        @foreach ($testAccounts as $name)
                            <li>
                                <span class="font-semibold">{{ $name }}</span>
                                @include('dev.action', ['action' => 'delete_account', 'fields' => ['username' => $name], 'label' => 'Supprimer', 'class' => 'btn btn-danger !min-h-9 !px-3 !text-sm'])
                            </li>
                        @endforeach
                    </ul>
                    <div class="lab-buttons">
                        @include('dev.action', ['action' => 'delete_all', 'label' => 'Supprimer tous les comptes de test', 'class' => 'btn btn-danger'])
                    </div>
                @else
                    <p class="text-muted">Aucun compte de test.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
