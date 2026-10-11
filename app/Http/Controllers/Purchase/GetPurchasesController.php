<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;

class GetPurchasesController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Purchase::class);

        $query = Purchase::with(['client', 'assignee', 'lines.item']);

        // Staff scoping: staff can only see purchases assigned to them
        $user = auth()->user();
        if ($user && $user->hasRole('staff') && ! $user->hasRole('admin') && ! $user->hasRole('manager')) {
            $query->where('assigned_to', $user->id);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQ) use ($search) {
                      $clientQ->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status Filter
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Priority Filter
        if ($priority = $request->input('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        // Return unpaginated if explicitly requested (e.g. for Kanban board aggregation)
        if ($request->input('view') === 'kanban' || $request->boolean('all')) {
            $allPurchases = $query->latest('created_at')->get();
            return response()->json([
                'data' => $allPurchases,
                'total' => $allPurchases->count(),
            ]);
        }

        $allowedSorts = ['id', 'purchase_number', 'purchase_date', 'total_amount', 'status', 'priority', 'created_at', 'updated_at'];
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