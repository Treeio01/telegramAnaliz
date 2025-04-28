<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Статистика' }}</title>
</head>

<body>
    <nav>
        <ul>
            <li><a href="/" class="{{ request()->is('/') ? 'active' : '' }}">📤 Загрузка архива</a></li>
            <li><a href="{{ route('stats.vendors') }}" class="{{ request()->is('stats/vendors') ? 'active' : '' }}">📊 Выживаемость</a></li>
            <li><a href="{{ route('stats.invites') }}" class="{{ request()->is('stats/invites') ? 'active' : '' }}">📈 Инвайты</a></li>
            
        </ul>
    </nav>
    <div class="container">
        @yield('content')
    </div>
</body>

</html>
<link rel="stylesheet" href="{{ asset('assets/style/style.css') }}">