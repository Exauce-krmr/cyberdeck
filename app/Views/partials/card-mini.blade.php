{{-- Version compacte d'une carte (choix du deck, pile adverse) : image, nom et les cinq valeurs. Variable : $card. --}}
<span class="mini rarity-{{ $card['rarity'] }}">
    <img class="mini-art" src="/img/cards/{{ $card['slug'] }}.png" alt="" loading="lazy" onerror="this.onerror=null;this.src='/img/placeholder.svg'">
    <span class="mini-name">{{ $card['name'] }}</span>
    <span class="mini-stats">
        @foreach ($attributes as $key => $label)
            <span title="{{ $label }}"><i>{{ mb_substr($label, 0, 3) }}</i>{{ $card[$key] }}</span>
        @endforeach
    </span>
</span>
