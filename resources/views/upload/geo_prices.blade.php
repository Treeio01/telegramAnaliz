@extends('layouts.app')

@section('content')
<h2>🧾 Укажите цену по GEO</h2>

<form method="POST" action="{{ route('upload.prices.apply', $upload->id) }}">
    @csrf

    @foreach ($geos as $geo)
    <label>{{ $geo }}:
        <input type="number" step="0.01" name="geo_prices[{{ $geo }}]"
            value="{{ $geoPrices[$geo] ?? '' }}" required>
    </label>
    @endforeach


    <button type="submit">💾 Применить</button>
</form>
@endsection