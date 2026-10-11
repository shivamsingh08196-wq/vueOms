<?php

namespace App\Http\Controllers\Item;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class GetItemsController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        // If all items requested without pagination (e.g. for selection dropdowns)
        if ($request->boolean('all') || $request->input('per_page') === 'all') {
            $allItems = Item::orderBy('name', 'asc')->get();
            return response()->json([
                'data' => $allItems,
                'total' => $allItems->count(),
            ]);
        }

        $query = Item::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($stockStatus = $request->input('stock_status')) {
            if ($stockStatus === 'in_stock') {
                $query->where('stock', '>', 5);
            } elseif ($stockStatus === 'low_stock') {
                $query->where('stock', '>', 0)->where('stock', '<=', 5);
            } elseif ($stockStatus === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            }
        }

        $allowedSorts = ['id', 'name', 'sku', 'price', 'stock', 'created_at', 'updated_at'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'created_at';
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        $perPage = max(1, min(100, (int) $request->input('per_page', 10)));
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }
}