{{-- Ta prochaine carte (celle qu'un Swap ferait entrer en main) et combien il t'en reste après. Variable : $pile. --}}
<h4 class="dock-title"><span>Ta prochaine carte</span>@if (count($pile) > 1)<small>+{{ count($pile) - 1 }} en réserve</small>@endif</h4>
@if (count($pile) > 0)
    @include('partials.card-chip', ['card' => $pile[0], 'next' => true])
@else
    <p class="dock-empty">Plus de réserve : ta main est ta dernière.</p>
@endif
