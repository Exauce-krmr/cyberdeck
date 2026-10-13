{{--
    LA carte, utilisée partout (partie, collection, récompense).
    Variables : $card, et en option $selectable (attributs cliquables), $highlight (attribut joué),
    $winner (true / false / null : gagnante, perdante, neutre), $shiny (skin shiny), $foe (valeurs adverses révélées par le Scan).
--}}
@php
    $selectable = $selectable ?? false;
    $highlight = $highlight ?? null;
    $winner = $winner ?? null;
    $shinyReady = $shinyReady ?? false; // skin débloqué mais peut-être pas équipé : on prépare le reflet pour pouvoir l'activer sans recharger
    $shiny = $shiny ?? false; // skin shiny équipé : variante visuelle de la carte (voir .is-shiny dans app.css)
    $foe = $foe ?? [];   // valeurs de la carte adverse révélées par le Scan : attribut => valeur
    $rarity = $rarities[$card['rarity']];
    $position = 0;
    $nameLength = mb_strlen($card['name']);
    $nameSize = $nameLength > 21 ? '4.1cqw' : ($nameLength > 16 ? '5cqw' : '6.2cqw'); // un nom reste sur une seule ligne : la carte ne grandit jamais
@endphp
<div class="card-wrap rarity-{{ $card['rarity'] }} {{ $shiny ? 'is-shiny' : '' }} {{ $selectable ? 'is-selectable' : '' }} {{ $winner === true ? 'is-winner' : '' }} {{ $winner === false ? 'is-loser' : '' }}">
    <article class="card {{ $rarity['holo'] ? 'holo' : '' }}" data-card="{{ $card['slug'] }}"{!! $card['rarity'] === 'legendary' ? ' data-tilt="20"' : '' !!}>
        @if ($shiny || $shinyReady)<span class="shiny-under" aria-hidden="true"><i class="shiny-frame"></i></span><span class="shiny-fx" aria-hidden="true" style="--shiny-delay: -{{ (crc32($card['slug']) % 45) / 10 }}s"><i class="shiny-foil"></i><i class="shiny-glint"></i><i class="shiny-star s1"></i><i class="shiny-star s2"></i><i class="shiny-star s3"></i><i class="shiny-star s4"></i><i class="shiny-star s5"></i><i class="shiny-star s6"></i></span>@endif
        <div class="card-body">
            <header class="card-head">
                <h3 class="card-name" style="--name-size: {{ $nameSize }}">{{ $card['name'] }}</h3>
                <span class="card-rarity">{{ $rarity['label'] }}</span>
            </header>

            <div class="card-art">
                <img
                    src="/img/cards/{{ $card['slug'] }}.png"
                    alt="{{ $card['name'] }}"
                    loading="lazy"
                    onerror="this.onerror=null;this.src='/img/placeholder.svg'"
                >
                <span class="card-faction">{{ $card['faction'] }}</span>
            </div>

            <p class="card-desc">{{ $card['description'] }}</p>

            <ul class="stats">
                @foreach ($attributes as $key => $label)
                    @php $position++; @endphp
                    <li>
                        <button type="button" data-attr="{{ $key }}" {{ $selectable ? '' : 'disabled' }}
                                class="stat {{ $highlight === $key ? 'is-picked' : '' }}">
                            <span class="stat-bar" style="width: {{ $card[$key] }}%"></span>
                            <span class="stat-label"><kbd class="stat-key">{{ $position }}</kbd>{{ $label }}</span>
                            <span class="stat-value">
                                @isset($foe[$key])<span class="stat-foe">adv. {{ $foe[$key] }}</span>@endisset
                                {{ $card[$key] }}
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    </article>
</div>
