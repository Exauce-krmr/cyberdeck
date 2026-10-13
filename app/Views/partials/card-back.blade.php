{{-- Carte face cachée : carte de l'adversaire avant la révélation, ou carte pas encore obtenue ($rarity la teinte). --}}
<div class="card-wrap rarity-{{ $rarity ?? 'common' }}">
    <article class="card card-back">
        <div class="card-body">
            <span class="card-back-mark">?</span>
            <span class="card-back-caption">{{ $caption ?? 'Carte cachée' }}</span>
        </div>
    </article>
</div>
