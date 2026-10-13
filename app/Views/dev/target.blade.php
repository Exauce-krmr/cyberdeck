{{-- Choix du compte visé par une action du Labo. Variable : $testAccounts. Vide = ton compte. --}}
<label class="lab-field">Compte
    <select name="target" class="field">
        <option value="">Mon compte</option>
        @foreach ($testAccounts as $name)
            <option value="{{ $name }}">{{ $name }}</option>
        @endforeach
    </select>
</label>
