@extends('layouts.app')

@section('content')
<h2>🧑 Профиль продавца: {{ $vendor->name }}</h2>

@include('partials.vendor-filters')

<hr>

<ul>
    <li><strong>Всего аккаунтов:</strong> {{ $total }}</li>
    <li><strong>Выжили:</strong> {{ $alive }}</li>
    <li><strong>Выживаемость:</strong> {{ $survival }}%</li>
    <li><strong>Инвайтов:</strong> {{ $total_invites }}</li>
    <li><strong>Потрачено:</strong> ${{ number_format($total_spent, 2) }}</li>
    <li><strong>Средняя цена инвайта:</strong> ${{ number_format($avg_invite_cost, 4) }}</li>
</ul>

<br><hr><br>

<h3>📋 Аккаунты продавца</h3>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th >Номер</th>
                <th>GEO</th>
                <th>Спам</th>
                <th>Дата сессии</th>
                <th>Последний коннект</th>
                <th>Инвайты</th>
                <th>Цена</th>
                <th>Тип</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($accounts as $acc)
                <tr>
                    <td>{{ $acc->phone }}</td>
                    <td>{{ $acc->geo }}</td>
                    <td>{{ $acc->spamblock ?? '-' }}</td>
                    <td>{{ $acc->session_created_at }}</td>
                    <td>{{ $acc->last_connect_at ?? '—' }}</td>
                    <td>{{ $acc->stats_invites_count }}</td>
                    <td>${{ number_format($acc->price, 2) }}</td>
                    <td>
                        @if ($acc->spamblock === 'free')
                            🟢 clean
                        @else
                            🔴 spam
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
