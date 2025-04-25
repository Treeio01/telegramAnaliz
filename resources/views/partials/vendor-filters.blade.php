<form method="GET">
    <label>Дата от:
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
    </label>
    <label>до:
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
    </label>

    <label>GEO:
        <select name="geo[]" multiple>
            @foreach($geos as $geo)
            <option value="{{ $geo }}" {{ in_array($geo, $filters['geo'] ?? []) ? 'selected' : '' }}>{{ $geo }}</option>
            @endforeach
        </select>
    </label>
    
    <label>Тип:
        <select name="type">
            <option value="total" {{ ($filters['type'] ?? 'total') === 'total' ? 'selected' : '' }}>total</option>
            <option value="spam" {{ ($filters['type'] ?? '') === 'spam' ? 'selected' : '' }}>spam</option>
            <option value="clean" {{ ($filters['type'] ?? '') === 'clean' ? 'selected' : '' }}>clean</option>
        </select>
    </label>

    @isset($highlight)
    <label><input type="checkbox" name="highlight" value="1" {{ $highlight ? 'checked' : '' }}> Подсветка</label>
    <label>Порог (%): <input type="number" step="0.1" name="survival_threshold" value="{{ $survivalThreshold }}"></label>
    <label>Мин. аккаунтов: <input type="number" name="min_accounts" value="{{ $minAccounts }}"></label>
    @endisset

    <button type="submit">🔍 Применить</button>
</form>