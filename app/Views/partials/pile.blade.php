{{-- Ce que tu sais de la pile de l'ordinateur : les cartes déjà vues (sans leur ordre) et combien te sont inconnues. --}}
<h4 class="dock-title"><span>Cartes vues de l'ordinateur</span><small>{{ count($known) + $unknown }} dans sa pile</small></h4>
<ul class="chips">
    @foreach ($known as $card)
        <li>@include('partials.card-chip', ['card' => $card])</li>
    @endforeach
    @if ($unknown > 0)
        <li><span class="chip chip-unknown">+ {{ $unknown }} inconnue(s)</span></li>
    @endif
    @if (count($known) + $unknown === 0)
        <li class="dock-empty">Sa pile est vide.</li>
    @endif
</ul>
