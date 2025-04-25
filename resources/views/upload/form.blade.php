@extends('layouts.app')

@section('content')
<h2>📦 Загрузка архива аккаунтов</h2>

@if (session('success'))
    <div style="color: green">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div style="color: red">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="/upload" method="POST" enctype="multipart/form-data">
    @csrf

    <label>Файл ZIP:
        <input type="file" name="zip_file" required>
    </label>

    <label>Тип архива:
        <select name="type" required>
            <option value="valid">Живые</option>
            <option value="dead">Мёртвые</option>
        </select>
    </label>

    <button type="submit">🚀 Загрузить</button>
</form>
@endsection
