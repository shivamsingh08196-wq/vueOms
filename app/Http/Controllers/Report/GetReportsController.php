<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Item;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class GetReportsController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        if (! $user || (! $user->can('reports.view') && ! $user->hasRole('admin') && ! $user->hasRole('manager'))) {
            abort(403, 'Unauthorized.');
        }

        $totalRevenue = Purchase::whereIn('status', ['confirmed', 'completed', 'approved', 'shipped'])->sum('total_amount');
        $totalPurchases = Purchase::count();
        $totalClients = Client::count();
        $totalItems = Item::count();

        $purchasesByStatus = Purchase::select('status', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as revenue'))
            ->groupBy('status')
            ->get();

        $topClients = Client::withCount('purchases')
            ->orderBy('purchases_count', 'desc')
            ->take(5)
            ->get();

        $recentPurchases = Purchase::with(['client', 'lines.item'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'summary' => [
                'total_revenue' => (float) $totalRevenue,
                'total_purchases' => $totalPurchases,
                'total_clients' => $totalClients,
                'total_items' => $totalItems,
            ],
            'purchases_by_status' => $purchasesByStatus,
            'top_clients' => $topClients,
            'recent_purchases' => $recentPurchases,
        ]);
    }
}
