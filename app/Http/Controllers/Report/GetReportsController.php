<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetReportsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('reports.view') && ! $user->hasRole('admin') && ! $user->hasRole('manager'))) {
            abort(403, 'Unauthorized.');
        }

        $applyFilters = function ($query, string $table = 'purchases') use ($request) {
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where("{$table}.status", $request->status);
            }
            if ($request->filled('year') && $request->year !== 'all') {
                $query->whereYear("{$table}.purchase_date", $request->year);
            }
            if ($request->filled('month') && $request->month !== 'all') {
                $query->whereMonth("{$table}.purchase_date", $request->month);
            }
            if ($request->filled('start_date')) {
                $query->whereDate("{$table}.purchase_date", '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate("{$table}.purchase_date", '<=', $request->end_date);
            }
            if ($request->filled('client_id') && $request->client_id !== 'all') {
                $query->where("{$table}.client_id", $request->client_id);
            }
            if ($request->filled('item_id') && $request->item_id !== 'all') {
                $query->whereExists(function ($sub) use ($request, $table) {
                    $sub->select(DB::raw(1))
                        ->from('purchase_lines')
                        ->whereColumn('purchase_lines.purchase_id', "{$table}.id")
                        ->where('purchase_lines.item_id', $request->item_id);
                });
            }
        };

        $basePurchasesQuery = DB::table('purchases');
        $applyFilters($basePurchasesQuery, 'purchases');

        $totalRevenue = (float) (clone $basePurchasesQuery)->sum('total_amount');
        $totalPurchases = (int) (clone $basePurchasesQuery)->count();
        $avgOrderValue = $totalPurchases > 0 ? ($totalRevenue / $totalPurchases) : 0;

        $itemsSoldQuery = DB::table('purchase_lines')
            ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id');
        $applyFilters($itemsSoldQuery, 'purchases');
        if ($request->filled('item_id') && $request->item_id !== 'all') {
            $itemsSoldQuery->where('purchase_lines.item_id', $request->item_id);
        }
        $totalItemsSold = (int) $itemsSoldQuery->sum('purchase_lines.quantity');

        // Status breakdown
        $statusQuery = DB::table('purchases');
        $applyFilters($statusQuery, 'purchases');
        $purchasesByStatus = $statusQuery
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as revenue'))
            ->groupBy('status')
            ->get();

        // 2. Top Products Sold by Quantity
        $topProductsQuantityQuery = DB::table('purchase_lines')
            ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id')
            ->join('items', 'purchase_lines.item_id', '=', 'items.id');
        $applyFilters($topProductsQuantityQuery, 'purchases');
        if ($request->filled('item_id') && $request->item_id !== 'all') {
            $topProductsQuantityQuery->where('items.id', $request->item_id);
        }
        $topProductsByQuantity = $topProductsQuantityQuery
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'items.sku',
                'items.price as unit_price',
                'items.stock as current_stock',
                DB::raw('SUM(purchase_lines.quantity) as total_quantity'),
                DB::raw('SUM(purchase_lines.subtotal) as total_revenue'),
                DB::raw('COUNT(DISTINCT purchases.id) as orders_count')
            )
            ->groupBy('items.id', 'items.name', 'items.sku', 'items.price', 'items.stock')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        // 3. Top Products Sold by Price (Revenue)
        $topProductsPriceQuery = DB::table('purchase_lines')
            ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id')
            ->join('items', 'purchase_lines.item_id', '=', 'items.id');
        $applyFilters($topProductsPriceQuery, 'purchases');
        if ($request->filled('item_id') && $request->item_id !== 'all') {
            $topProductsPriceQuery->where('items.id', $request->item_id);
        }
        $topProductsByPrice = $topProductsPriceQuery
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'items.sku',
                'items.price as unit_price',
                'items.stock as current_stock',
                DB::raw('SUM(purchase_lines.quantity) as total_quantity'),
                DB::raw('SUM(purchase_lines.subtotal) as total_revenue'),
                DB::raw('COUNT(DISTINCT purchases.id) as orders_count')
            )
            ->groupBy('items.id', 'items.name', 'items.sku', 'items.price', 'items.stock')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // 4. Top Clients by Orders Count
        $topClientsOrdersQuery = DB::table('purchases')
            ->join('clients', 'purchases.client_id', '=', 'clients.id');
        $applyFilters($topClientsOrdersQuery, 'purchases');
        $topClientsByOrders = $topClientsOrdersQuery
            ->select(
                'clients.id as client_id',
                'clients.name as client_name',
                'clients.name as name',
                'clients.email',
                'clients.phone',
                DB::raw('COUNT(purchases.id) as orders_count'),
                DB::raw('COUNT(purchases.id) as purchases_count'),
                DB::raw('SUM(purchases.total_amount) as total_spent')
            )
            ->groupBy('clients.id', 'clients.name', 'clients.email', 'clients.phone')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        // 5. Top Clients by Order Price (Total Spend)
        $topClientsPriceQuery = DB::table('purchases')
            ->join('clients', 'purchases.client_id', '=', 'clients.id');
        $applyFilters($topClientsPriceQuery, 'purchases');
        $topClientsByPrice = $topClientsPriceQuery
            ->select(
                'clients.id as client_id',
                'clients.name as client_name',
                'clients.name as name',
                'clients.email',
                'clients.phone',
                DB::raw('COUNT(purchases.id) as orders_count'),
                DB::raw('COUNT(purchases.id) as purchases_count'),
                DB::raw('SUM(purchases.total_amount) as total_spent')
            )
            ->groupBy('clients.id', 'clients.name', 'clients.email', 'clients.phone')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        // 6. Per Client Detailed Report
        $clientPurchasesSub = DB::table('purchases');
        $applyFilters($clientPurchasesSub, 'purchases');
        $clientStats = $clientPurchasesSub
            ->select(
                'purchases.client_id',
                DB::raw('COUNT(purchases.id) as orders_count'),
                DB::raw('SUM(purchases.total_amount) as total_spent'),
                DB::raw('AVG(purchases.total_amount) as avg_order_value'),
                DB::raw('MAX(purchases.purchase_date) as latest_purchase_date')
            )
            ->groupBy('purchases.client_id');

        $clientQtySub = DB::table('purchase_lines')
            ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id');
        $applyFilters($clientQtySub, 'purchases');
        $clientQtyStats = $clientQtySub
            ->select('purchases.client_id', DB::raw('SUM(purchase_lines.quantity) as total_quantity'))
            ->groupBy('purchases.client_id');

        $perClientQuery = DB::table('clients')
            ->joinSub($clientStats, 'p_stats', 'clients.id', '=', 'p_stats.client_id')
            ->leftJoinSub($clientQtyStats, 'q_stats', 'clients.id', '=', 'q_stats.client_id');

        if ($request->filled('client_id') && $request->client_id !== 'all') {
            $perClientQuery->where('clients.id', $request->client_id);
        }

        $perClientReport = $perClientQuery
            ->select(
                'clients.id as client_id',
                'clients.name as client_name',
                'clients.email',
                'clients.phone',
                'p_stats.orders_count',
                'p_stats.total_spent',
                'p_stats.avg_order_value',
                'p_stats.latest_purchase_date',
                DB::raw('COALESCE(q_stats.total_quantity, 0) as total_units_purchased')
            )
            ->orderByDesc('p_stats.total_spent')
            ->get();

        // 7. Per Product Detailed Report
        $itemLinesSub = DB::table('purchase_lines')
            ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id');
        $applyFilters($itemLinesSub, 'purchases');
        $itemStats = $itemLinesSub
            ->select(
                'purchase_lines.item_id',
                DB::raw('COUNT(DISTINCT purchases.id) as orders_count'),
                DB::raw('SUM(purchase_lines.quantity) as total_quantity_sold'),
                DB::raw('SUM(purchase_lines.subtotal) as total_revenue')
            )
            ->groupBy('purchase_lines.item_id');

        $perProductQuery = DB::table('items')
            ->joinSub($itemStats, 'i_stats', 'items.id', '=', 'i_stats.item_id');

        if ($request->filled('item_id') && $request->item_id !== 'all') {
            $perProductQuery->where('items.id', $request->item_id);
        }

        $perProductReport = $perProductQuery
            ->select(
                'items.id as item_id',
                'items.name as item_name',
                'items.sku',
                'items.price as unit_price',
                'items.stock as current_stock',
                'i_stats.orders_count',
                'i_stats.total_quantity_sold',
                'i_stats.total_revenue'
            )
            ->orderByDesc('i_stats.total_revenue')
            ->get();

        // 8. Filter Metadata Options
        $years = DB::table('purchases')
            ->select(DB::raw('DISTINCT YEAR(purchase_date) as year'))
            ->whereNotNull('purchase_date')
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->values();

        $allClients = Client::select('id', 'name', 'email')->orderBy('name')->get();
        $allItems = Item::select('id', 'name', 'sku')->orderBy('name')->get();

        return response()->json([
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_purchases' => $totalPurchases,
                'total_clients' => Client::count(),
                'total_items' => Item::count(),
                'total_items_sold' => $totalItemsSold,
                'avg_order_value' => round($avgOrderValue, 2),
            ],
            'purchases_by_status' => $purchasesByStatus,
            'top_clients' => $topClientsByOrders, // For existing test assertions
            'top_products_by_quantity' => $topProductsByQuantity,
            'top_products_by_price' => $topProductsByPrice,
            'top_clients_by_orders' => $topClientsByOrders,
            'top_clients_by_price' => $topClientsByPrice,
            'per_client_report' => $perClientReport,
            'per_product_report' => $perProductReport,
            'filter_options' => [
                'years' => $years->isNotEmpty() ? $years : ['2026', '2025'],
                'months' => [
                    ['value' => '1', 'name' => 'January'],
                    ['value' => '2', 'name' => 'February'],
                    ['value' => '3', 'name' => 'March'],
                    ['value' => '4', 'name' => 'April'],
                    ['value' => '5', 'name' => 'May'],
                    ['value' => '6', 'name' => 'June'],
                    ['value' => '7', 'name' => 'July'],
                    ['value' => '8', 'name' => 'August'],
                    ['value' => '9', 'name' => 'September'],
                    ['value' => '10', 'name' => 'October'],
                    ['value' => '11', 'name' => 'November'],
                    ['value' => '12', 'name' => 'December'],
                ],
                'statuses' => ['pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled'],
                'clients' => $allClients,
                'items' => $allItems,
            ],
        ]);
    }
}
