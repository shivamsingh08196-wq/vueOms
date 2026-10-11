<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('reports.view') && ! $user->hasRole('admin') && ! $user->hasRole('manager'))) {
            abort(403, 'Unauthorized.');
        }

        $type = $request->query('type', 'overview');
        $format = strtolower($request->query('format', 'csv'));

        // Shared filter closure
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

        // Summary KPI calculations
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

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_purchases' => $totalPurchases,
            'total_items_sold' => $totalItemsSold,
            'avg_order_value' => round($avgOrderValue, 2),
        ];

        $headers = [];
        $rows = [];
        $viewName = 'Operational Report';
        $tableTitle = 'Report Details';
        $filePrefix = 'report';

        switch ($type) {
            case 'top_products_quantity':
                $viewName = 'Top Products Sold by Quantity';
                $tableTitle = 'Ranking: Top Catalog Items by Units Sold';
                $filePrefix = 'top-products-by-quantity';
                $headers = [
                    ['key' => 'rank', 'label' => 'Rank', 'align' => 'center'],
                    ['key' => 'name', 'label' => 'Product Name'],
                    ['key' => 'sku', 'label' => 'SKU'],
                    ['key' => 'unit_price', 'label' => 'Unit Price ($)', 'align' => 'right'],
                    ['key' => 'total_quantity', 'label' => 'Units Sold (Qty)', 'align' => 'right'],
                    ['key' => 'total_revenue', 'label' => 'Total Revenue ($)', 'align' => 'right'],
                    ['key' => 'orders_count', 'label' => 'Orders Count', 'align' => 'center'],
                ];
                $data = DB::table('purchase_lines')
                    ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id')
                    ->join('items', 'purchase_lines.item_id', '=', 'items.id')
                    ->when(true, fn($q) => $applyFilters($q, 'purchases'))
                    ->when($request->filled('item_id') && $request->item_id !== 'all', fn($q) => $q->where('items.id', $request->item_id))
                    ->select(
                        'items.name',
                        'items.sku',
                        'items.price as unit_price',
                        DB::raw('SUM(purchase_lines.quantity) as total_quantity'),
                        DB::raw('SUM(purchase_lines.subtotal) as total_revenue'),
                        DB::raw('COUNT(DISTINCT purchases.id) as orders_count')
                    )
                    ->groupBy('items.id', 'items.name', 'items.sku', 'items.price')
                    ->orderByDesc('total_quantity')
                    ->limit(50)
                    ->get();

                $rank = 1;
                foreach ($data as $item) {
                    $rows[] = [
                        'rank' => '#' . $rank++,
                        'name' => $item->name,
                        'sku' => $item->sku ?? 'N/A',
                        'unit_price' => '$' . number_format($item->unit_price, 2),
                        'total_quantity' => number_format($item->total_quantity),
                        'total_revenue' => '$' . number_format($item->total_revenue, 2),
                        'orders_count' => $item->orders_count,
                    ];
                }
                break;

            case 'top_products_price':
                $viewName = 'Top Products Sold by Revenue';
                $tableTitle = 'Ranking: Top Catalog Items by Gross Sales';
                $filePrefix = 'top-products-by-revenue';
                $headers = [
                    ['key' => 'rank', 'label' => 'Rank', 'align' => 'center'],
                    ['key' => 'name', 'label' => 'Product Name'],
                    ['key' => 'sku', 'label' => 'SKU'],
                    ['key' => 'unit_price', 'label' => 'Unit Price ($)', 'align' => 'right'],
                    ['key' => 'total_revenue', 'label' => 'Total Revenue ($)', 'align' => 'right'],
                    ['key' => 'total_quantity', 'label' => 'Units Sold (Qty)', 'align' => 'right'],
                    ['key' => 'orders_count', 'label' => 'Orders Count', 'align' => 'center'],
                ];
                $data = DB::table('purchase_lines')
                    ->join('purchases', 'purchase_lines.purchase_id', '=', 'purchases.id')
                    ->join('items', 'purchase_lines.item_id', '=', 'items.id')
                    ->when(true, fn($q) => $applyFilters($q, 'purchases'))
                    ->when($request->filled('item_id') && $request->item_id !== 'all', fn($q) => $q->where('items.id', $request->item_id))
                    ->select(
                        'items.name',
                        'items.sku',
                        'items.price as unit_price',
                        DB::raw('SUM(purchase_lines.quantity) as total_quantity'),
                        DB::raw('SUM(purchase_lines.subtotal) as total_revenue'),
                        DB::raw('COUNT(DISTINCT purchases.id) as orders_count')
                    )
                    ->groupBy('items.id', 'items.name', 'items.sku', 'items.price')
                    ->orderByDesc('total_revenue')
                    ->limit(50)
                    ->get();

                $rank = 1;
                foreach ($data as $item) {
                    $rows[] = [
                        'rank' => '#' . $rank++,
                        'name' => $item->name,
                        'sku' => $item->sku ?? 'N/A',
                        'unit_price' => '$' . number_format($item->unit_price, 2),
                        'total_revenue' => '$' . number_format($item->total_revenue, 2),
                        'total_quantity' => number_format($item->total_quantity),
                        'orders_count' => $item->orders_count,
                    ];
                }
                break;

            case 'top_clients_orders':
                $viewName = 'Top Clients by Order Count';
                $tableTitle = 'Ranking: Top Clients by Purchases Volume';
                $filePrefix = 'top-clients-by-orders';
                $headers = [
                    ['key' => 'rank', 'label' => 'Rank', 'align' => 'center'],
                    ['key' => 'name', 'label' => 'Client Name'],
                    ['key' => 'email', 'label' => 'Email Address'],
                    ['key' => 'phone', 'label' => 'Phone'],
                    ['key' => 'orders_count', 'label' => 'Orders Count', 'align' => 'center'],
                    ['key' => 'total_spent', 'label' => 'Total Spent ($)', 'align' => 'right'],
                ];
                $data = DB::table('purchases')
                    ->join('clients', 'purchases.client_id', '=', 'clients.id')
                    ->when(true, fn($q) => $applyFilters($q, 'purchases'))
                    ->select(
                        'clients.name',
                        'clients.email',
                        'clients.phone',
                        DB::raw('COUNT(purchases.id) as orders_count'),
                        DB::raw('SUM(purchases.total_amount) as total_spent')
                    )
                    ->groupBy('clients.id', 'clients.name', 'clients.email', 'clients.phone')
                    ->orderByDesc('orders_count')
                    ->limit(50)
                    ->get();

                $rank = 1;
                foreach ($data as $c) {
                    $rows[] = [
                        'rank' => '#' . $rank++,
                        'name' => $c->name,
                        'email' => $c->email,
                        'phone' => $c->phone ?? 'N/A',
                        'orders_count' => $c->orders_count,
                        'total_spent' => '$' . number_format($c->total_spent, 2),
                    ];
                }
                break;

            case 'top_clients_price':
                $viewName = 'Top Clients by Order Value';
                $tableTitle = 'Ranking: Top Clients by Total Spend';
                $filePrefix = 'top-clients-by-revenue';
                $headers = [
                    ['key' => 'rank', 'label' => 'Rank', 'align' => 'center'],
                    ['key' => 'name', 'label' => 'Client Name'],
                    ['key' => 'email', 'label' => 'Email Address'],
                    ['key' => 'phone', 'label' => 'Phone'],
                    ['key' => 'total_spent', 'label' => 'Total Spent ($)', 'align' => 'right'],
                    ['key' => 'orders_count', 'label' => 'Orders Count', 'align' => 'center'],
                ];
                $data = DB::table('purchases')
                    ->join('clients', 'purchases.client_id', '=', 'clients.id')
                    ->when(true, fn($q) => $applyFilters($q, 'purchases'))
                    ->select(
                        'clients.name',
                        'clients.email',
                        'clients.phone',
                        DB::raw('COUNT(purchases.id) as orders_count'),
                        DB::raw('SUM(purchases.total_amount) as total_spent')
                    )
                    ->groupBy('clients.id', 'clients.name', 'clients.email', 'clients.phone')
                    ->orderByDesc('total_spent')
                    ->limit(50)
                    ->get();

                $rank = 1;
                foreach ($data as $c) {
                    $rows[] = [
                        'rank' => '#' . $rank++,
                        'name' => $c->name,
                        'email' => $c->email,
                        'phone' => $c->phone ?? 'N/A',
                        'total_spent' => '$' . number_format($c->total_spent, 2),
                        'orders_count' => $c->orders_count,
                    ];
                }
                break;

            case 'per_client':
                $viewName = 'Detailed Client Performance Report';
                $tableTitle = 'Comprehensive Client Performance Metrics';
                $filePrefix = 'client-performance-report';
                $headers = [
                    ['key' => 'client_id', 'label' => 'Client ID', 'align' => 'center'],
                    ['key' => 'name', 'label' => 'Client Name'],
                    ['key' => 'email', 'label' => 'Email Address'],
                    ['key' => 'phone', 'label' => 'Phone Number'],
                    ['key' => 'orders_count', 'label' => 'Orders Count', 'align' => 'center'],
                    ['key' => 'total_units', 'label' => 'Units Bought', 'align' => 'right'],
                    ['key' => 'total_spent', 'label' => 'Total Spent ($)', 'align' => 'right'],
                    ['key' => 'avg_order', 'label' => 'Avg Order ($)', 'align' => 'right'],
                    ['key' => 'latest_date', 'label' => 'Latest Purchase', 'align' => 'center'],
                ];

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

                $query = DB::table('clients')
                    ->joinSub($clientStats, 'p_stats', 'clients.id', '=', 'p_stats.client_id')
                    ->leftJoinSub($clientQtyStats, 'q_stats', 'clients.id', '=', 'q_stats.client_id');

                if ($request->filled('client_id') && $request->client_id !== 'all') {
                    $query->where('clients.id', $request->client_id);
                }

                $data = $query
                    ->select(
                        'clients.id as client_id',
                        'clients.name',
                        'clients.email',
                        'clients.phone',
                        'p_stats.orders_count',
                        'p_stats.total_spent',
                        'p_stats.avg_order_value',
                        'p_stats.latest_purchase_date',
                        DB::raw('COALESCE(q_stats.total_quantity, 0) as total_quantity')
                    )
                    ->orderByDesc('p_stats.total_spent')
                    ->get();

                foreach ($data as $c) {
                    $rows[] = [
                        'client_id' => '#' . $c->client_id,
                        'name' => $c->name,
                        'email' => $c->email,
                        'phone' => $c->phone ?? 'N/A',
                        'orders_count' => $c->orders_count,
                        'total_units' => number_format($c->total_quantity),
                        'total_spent' => '$' . number_format($c->total_spent, 2),
                        'avg_order' => '$' . number_format($c->avg_order_value, 2),
                        'latest_date' => $c->latest_purchase_date ?? 'N/A',
                    ];
                }
                break;

            case 'per_product':
                $viewName = 'Detailed Product Performance Report';
                $tableTitle = 'Comprehensive Catalog Item Performance Metrics';
                $filePrefix = 'product-performance-report';
                $headers = [
                    ['key' => 'item_id', 'label' => 'Item ID', 'align' => 'center'],
                    ['key' => 'name', 'label' => 'Product Name'],
                    ['key' => 'sku', 'label' => 'SKU'],
                    ['key' => 'price', 'label' => 'Unit Price ($)', 'align' => 'right'],
                    ['key' => 'stock', 'label' => 'Stock', 'align' => 'center'],
                    ['key' => 'orders_count', 'label' => 'Orders Count', 'align' => 'center'],
                    ['key' => 'total_qty', 'label' => 'Units Sold (Qty)', 'align' => 'right'],
                    ['key' => 'total_revenue', 'label' => 'Total Revenue ($)', 'align' => 'right'],
                ];

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

                $query = DB::table('items')
                    ->joinSub($itemStats, 'i_stats', 'items.id', '=', 'i_stats.item_id');

                if ($request->filled('item_id') && $request->item_id !== 'all') {
                    $query->where('items.id', $request->item_id);
                }

                $data = $query
                    ->select(
                        'items.id as item_id',
                        'items.name',
                        'items.sku',
                        'items.price',
                        'items.stock',
                        'i_stats.orders_count',
                        'i_stats.total_quantity_sold',
                        'i_stats.total_revenue'
                    )
                    ->orderByDesc('i_stats.total_revenue')
                    ->get();

                foreach ($data as $i) {
                    $rows[] = [
                        'item_id' => '#' . $i->item_id,
                        'name' => $i->name,
                        'sku' => $i->sku ?? 'N/A',
                        'price' => '$' . number_format($i->price, 2),
                        'stock' => $i->stock,
                        'orders_count' => $i->orders_count,
                        'total_qty' => number_format($i->total_quantity_sold),
                        'total_revenue' => '$' . number_format($i->total_revenue, 2),
                    ];
                }
                break;

            case 'overview':
            default:
                $viewName = 'Executive Operational Overview';
                $tableTitle = 'Summary of Key Performance Indicators & Status Velocity';
                $filePrefix = 'operational-summary-report';
                $headers = [
                    ['key' => 'status', 'label' => 'Order Status'],
                    ['key' => 'count', 'label' => 'Purchases Count', 'align' => 'center'],
                    ['key' => 'revenue', 'label' => 'Revenue ($)', 'align' => 'right'],
                ];
                $data = DB::table('purchases')
                    ->when(true, fn($q) => $applyFilters($q, 'purchases'))
                    ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as revenue'))
                    ->groupBy('status')
                    ->get();

                foreach ($data as $st) {
                    $rows[] = [
                        'status' => ucfirst($st->status),
                        'count' => $st->count,
                        'revenue' => '$' . number_format($st->revenue ?? 0, 2),
                    ];
                }
                break;
        }

        $filtersDesc = [
            'year' => $request->filled('year') && $request->year !== 'all' ? $request->year : 'All Years',
            'month' => $request->filled('month') && $request->month !== 'all' ? date('F', mktime(0, 0, 0, (int)$request->month, 10)) : 'All Months',
            'date_range' => ($request->filled('start_date') || $request->filled('end_date'))
                ? ($request->start_date ?? 'Start') . ' to ' . ($request->end_date ?? 'Present')
                : 'All Time History',
            'status' => $request->filled('status') && $request->status !== 'all' ? $request->status : 'All Statuses',
        ];

        $timestamp = now()->format('Ymd_His');
        $fileName = "{$filePrefix}_{$timestamp}";

        // --- 1. CSV EXPORT ---
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                // Write UTF-8 BOM for Microsoft Excel compatibility
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_column($headers, 'label'));
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($headers as $h) {
                        $line[] = $row[$h['key']] ?? '';
                    }
                    fputcsv($out, $line);
                }
                fclose($out);
            }, "{$fileName}.csv", [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        }

        // --- 2. XLS EXPORT (Excel 97-2003 BIFF8) ---
        if ($format === 'xls') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($viewName, 0, 31));

            $colIdx = 1;
            foreach ($headers as $h) {
                $sheet->setCellValueExplicit([$colIdx, 1], $h['label'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                $colIdx++;
            }

            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

            $rowIdx = 2;
            foreach ($rows as $row) {
                $colIdx = 1;
                foreach ($headers as $h) {
                    $sheet->setCellValueExplicit([$colIdx, $rowIdx], (string)($row[$h['key']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $colIdx++;
                }
                $rowIdx++;
            }

            $writer = new Xls($spreadsheet);
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, "{$fileName}.xls", [
                'Content-Type' => 'application/vnd.ms-excel',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        }

        // --- 3. XLSX EXPORT (Office Open XML) ---
        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($viewName, 0, 31));

            $colIdx = 1;
            foreach ($headers as $h) {
                $sheet->setCellValueExplicit([$colIdx, 1], $h['label'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                $colIdx++;
            }

            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B362A');

            $rowIdx = 2;
            foreach ($rows as $row) {
                $colIdx = 1;
                foreach ($headers as $h) {
                    $sheet->setCellValueExplicit([$colIdx, $rowIdx], (string)($row[$h['key']] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $colIdx++;
                }
                $rowIdx++;
            }

            $writer = new Xlsx($spreadsheet);
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, "{$fileName}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ]);
        }

        // --- 4. PDF EXPORT ---
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.pdf', [
                'title' => $viewName,
                'view_name' => $viewName,
                'table_title' => $tableTitle,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filters' => $filtersDesc,
                'summary' => $summary,
                'headers' => $headers,
                'rows' => $rows,
            ]);

            return $pdf->download("{$fileName}.pdf");
        }

        abort(400, "Unsupported export format: {$format}");
    }
}
