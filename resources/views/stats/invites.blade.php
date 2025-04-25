@extends('layouts.app')

@section('content')
<h2>📈 Инвайт-статистика по продавцам</h2>

@include('partials.vendor-filters')

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Продавец</th>
                <th>Аккаунтов с инвайтами</th>
                <th>Инвайтов всего</th>
                <th>Потрачено</th>
                <th>Средняя цена инвайта</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stats as $stat)
                <tr>
                    <td>{{ $stat['vendor'] }}</td>
                    <td>{{ $stat['accounts_used'] }}</td>
                    <td>{{ $stat['invites'] }}</td>
                    <td>${{ number_format($stat['spent'], 2) }}</td>
                    <td>${{ number_format($stat['avg_per_invite'], 4) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
