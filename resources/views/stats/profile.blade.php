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
@endsection
