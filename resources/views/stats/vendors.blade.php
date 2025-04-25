@extends('layouts.app')

@section('content')
<h2>📊 Статистика выживаемости по продавцам</h2>

@include('partials.vendor-filters')

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Продавец</th>
                <th>Всего аккаунтов</th>
                <th>Выжили</th>
                <th>Выживаемость</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stats as $stat)
            <tr class="{{ $highlight && $stat['total'] >= $minAccounts && $stat['survival_rate'] < $survivalThreshold ? 'highlight' : '' }}">
                <td>
                    <strong>{{ $stat['vendor_name'] }}</strong><br>
                    <a href="{{ route('vendor.profile', ['vendor' => $stat['vendor_id']]) }}">👁 Профиль</a>
                </td>
                <td>{{ $stat['total'] }}</td>
                <td>{{ $stat['alive'] }}</td>
                <td>{{ $stat['survival_rate'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection