@extends('layouts.app')

@section('content')
<h2>📈 Инвайт-статистика по продавцам</h2>

@include('partials.vendor-filters')

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Продавец</th>
                <th>Номер</th>
                <th>GEO</th>
                <th>Спам</th>
                <th>Тип</th>
                <th>Инвайты</th>
                <th>Цена</th>
                <th>Создан</th>
                <th>Последний вход</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($accounts as $acc)
                <tr>
                    <td>{{ $acc->vendor->name ?? '—' }}</td>
                    <td>{{ $acc->phone }}</td>
                    <td>{{ $acc->geo }}</td>
                    <td>{{ $acc->spamblock ?? '-' }}</td>
                    <td>
                        @if ($acc->spamblock === 'free')
                            🟢 clean
                        @else
                            🔴 spam
                        @endif
                    </td>
                    <td>{{ $acc->stats_invites_count }}</td>
                    <td>${{ number_format($acc->price, 2) }}</td>
                    <td>{{ $acc->session_created_at }}</td>
                    <td>{{ $acc->last_connect_at ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
