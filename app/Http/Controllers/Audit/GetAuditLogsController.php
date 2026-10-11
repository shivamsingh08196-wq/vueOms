<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class GetAuditLogsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('audit.view') && ! $user->hasRole('admin'))) {
            abort(403, 'Unauthorized.');
        }

        $logs = AuditLog::with('user')
            ->when($request->action, fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json($logs);
    }
}
