<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\GeoPrice;

class StatsController extends Controller
{
    public function vendorProfile(Request $request, Vendor $vendor)
    {
        $accounts = Account::where('vendor_id', $vendor->id);

        if ($request->filled('geo')) {
            $accounts->whereIn('geo', (array)$request->input('geo'));
        }

        if ($request->filled('from')) {
            $accounts->where('session_created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $accounts->where('session_created_at', '<=', $request->input('to'));
        }

        $accounts = $accounts->get();

        $total = $accounts->count();
        $alive = $accounts->whereNotNull('last_connect_at')->count();
        $survival = $total > 0 ? round($alive / $total * 100, 2) : 0;

        $totalInvites = $accounts->sum('stats_invites_count');

        $totalSpent = $accounts->sum(function ($acc) {
            return $acc->price ?? GeoPrice::where('geo', $acc->geo)->value('price') ?? 0;
        });

        $avgInviteCost = $totalInvites > 0 ? round($totalSpent / $totalInvites, 4) : 0;

        $geos = Account::select('geo')->distinct()->pluck('geo')->filter()->sort()->values();

        return view('stats.profile', [
            'vendor' => $vendor,
            'total' => $total,
            'alive' => $alive,
            'survival' => $survival,
            'total_invites' => $totalInvites,
            'total_spent' => $totalSpent,
            'avg_invite_cost' => $avgInviteCost,
            'filters' => $request->only(['geo', 'from', 'to', 'type']),
            'geos' => $geos,
        ]);
    }

    public function vendorStats(Request $request)
    {
        // Фильтры
        $geos = Account::select('geo')->distinct()->pluck('geo')->filter()->sort()->values();
        $highlight = $request->boolean('highlight');
        $minAccounts = $request->input('min_accounts', 3);
        $survivalThreshold = $request->input('survival_threshold', 30);

        // Сбор данных
        $query = Account::query()
            ->selectRaw('vendor_id, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN last_connect_at IS NOT NULL THEN 1 ELSE 0 END) as alive")
            ->selectRaw("ROUND(SUM(CASE WHEN last_connect_at IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as survival_rate")
            ->groupBy('vendor_id');

        $type = $request->input('type', 'total');

        if ($type === 'spam') {
            $query->where('spamblock', '!=', 'free'); // всё что не free — это спам
        } elseif ($type === 'clean') {
            $query->where('spamblock', 'free'); // только "free" — это чистые
        }

        if ($request->filled('geo')) {
            $query->whereIn('geo', (array)$request->input('geo'));
        }

        if ($request->filled('from')) {
            $query->where('session_created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('session_created_at', '<=', $request->input('to'));
        }


        $stats = $query->get()->map(function ($row) {
            $vendor = Vendor::find($row->vendor_id);
            return [
                'vendor_id' => $vendor?->id ?? 'unknown',
                'vendor_name' => $vendor?->name ?? 'unknown',
                'total' => $row->total,
                'alive' => $row->alive,
                'survival_rate' => (float)$row->survival_rate,
            ];
        })->filter(function ($stat) use ($minAccounts, $survivalThreshold) {
            return $stat['total'] >= $minAccounts && $stat['survival_rate'] >= $survivalThreshold;
        });

        return view('stats.vendors', [
            'stats' => $stats,
            'geos' => $geos,
            'filters' => $request->only(['geo', 'from', 'to', 'type']),
            'highlight' => $highlight,
            'minAccounts' => $minAccounts,
            'survivalThreshold' => $survivalThreshold,
        ]);
    }

    public function inviteStats(Request $request)
    {
        $query = Account::query()
            ->with('vendor')
            ->select('vendor_id', 'geo', 'price', 'stats_invites_count')
            ->where('stats_invites_count', '>', 0);

        $type = $request->input('type', 'total');

        if ($type === 'spam') {
            $query->where('spamblock', '!=', 'free'); // всё что не free — это спам
        } elseif ($type === 'clean') {
            $query->where('spamblock', 'free'); // только "free" — это чистые
        }

        if ($request->filled('geo')) {
            $query->whereIn('geo', (array) $request->input('geo'));
        }

        if ($request->filled('from')) {
            $query->where('session_created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('session_created_at', '<=', $request->input('to'));
        }

        $accounts = $query->get();

        $grouped = $accounts->groupBy('vendor_id');

        $stats = $grouped->map(function ($accounts, $vendorId) {
            $vendorName = optional($accounts->first()->vendor)->name ?? 'unknown';

            $totalAccounts = $accounts->count();
            $totalInvites = $accounts->sum('stats_invites_count');

            $totalSpent = $accounts->sum(function ($acc) {
                return $acc->price ?? GeoPrice::where('geo', $acc->geo)->value('price') ?? 0;
            });

            $avgCostPerInvite = $totalInvites > 0 ? round($totalSpent / $totalInvites, 4) : 0;

            return [
                'vendor' => $vendorName,
                'accounts_used' => $totalAccounts,
                'invites' => $totalInvites,
                'spent' => $totalSpent,
                'avg_per_invite' => $avgCostPerInvite,
            ];
        })->values();

        $geos = Account::select('geo')->distinct()->pluck('geo')->filter()->sort()->values();

        return view('stats.invites', [
            'stats' => $stats,
            'geos' => $geos,
            'filters' => $request->only(['geo', 'from', 'to', 'type']),
        ]);
    }
}
