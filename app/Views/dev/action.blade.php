{{-- Un bouton qui lance une action du Labo. Variables : $action, $label, $fields = champs cachés (nom => valeur), $class = classes du bouton. --}}
<form method="post" action="/dev" class="lab-inline">
    <input type="hidden" name="_csrf" value="{{ $csrf }}">
    <input type="hidden" name="action" value="{{ $action }}">
    @foreach ($fields ?? [] as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <button class="{{ $class ?? 'btn btn-ghost' }}">{{ $label }}</button>
</form>
