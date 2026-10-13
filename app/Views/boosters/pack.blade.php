{{-- Un paquet. Variable : $pack = expression Alpine de l'objet booster ({ kind, difficulty }), $extra = classes en plus. --}}
<div class="pack {{ $extra ?? '' }}" data-tilt="20" :data-kind="{{ $pack }}.kind" :data-difficulty="{{ $pack }}.difficulty" :data-tone="tone({{ $pack }})">
    <span class="pack-foil" aria-hidden="true"></span>
    <span class="pack-crimp pack-crimp-top" aria-hidden="true"></span>
    <span class="pack-emblem" aria-hidden="true"></span>
    <span class="pack-level" x-text="level({{ $pack }})" :data-help-tone="helpTone({{ $pack }})" :data-help-title="helpTitle({{ $pack }})" :data-help="helpText({{ $pack }})"></span>
    <strong class="pack-title" x-text="title({{ $pack }})"></strong>
    <span class="pack-sub" x-text="describe({{ $pack }})"></span>
    <span class="pack-glare" aria-hidden="true"></span>
    <span class="pack-crimp pack-crimp-bottom" aria-hidden="true"></span>
</div>
