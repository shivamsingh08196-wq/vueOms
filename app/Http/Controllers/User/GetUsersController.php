<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class GetUsersController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // If all users requested without pagination (e.g. for assignment dropdowns)
        if ($request->boolean('all') || $request->input('per_page') === 'all') {
            $allUsers = User::with('roles')->orderBy('name', 'asc')->get();
            return response()->json([
                'data' => $allUsers,
                'total' => $allUsers->count(),
            ]);
        }

        $query = User::with('roles');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'name', 'email', 'created_at', 'updated_at'];
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
