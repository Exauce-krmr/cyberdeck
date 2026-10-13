{{-- Puce compacte d'une carte (réserves en bas de la table) : miniature, nom et les cinq valeurs. Variables : $card, $next (facultatif). --}}
<span class="chip rarity-{{ $card['rarity'] }} {{ ($next ?? false) ? 'chip-next' : '' }}">
    <img class="chip-art" src="/img/cards/{{ $card['slug'] }}.png" alt="" loading="lazy" onerror="this.onerror=null;this.src='/img/placeholder.svg'">
    <span class="chip-body">
        <b class="chip-name">{{ $card['name'] }}</b>
        <span class="chip-stats">
            @foreach ($attributes as $key => $label)
                <span title="{{ $label }}"><i>{{ mb_substr($label, 0, 3) }}</i>{{ $card[$key] }}</span>
            @endforeach
        </span>
    </span>
</span>
