{{-- Liste déroulante des difficultés. Variables : $difficulties, $field (nom du champ, « difficulty » par défaut). --}}
<select name="{{ $field ?? 'difficulty' }}" class="field">
    @foreach ($difficulties as $key => $difficulty)
        <option value="{{ $key }}">{{ $difficulty['label'] }}</option>
    @endforeach
</select>
