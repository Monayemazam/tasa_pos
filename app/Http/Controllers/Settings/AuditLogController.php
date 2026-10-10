<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit logs (strictly read-only).
     */
    public function index(Request $request): Response
    {
        // Enforce settings.manage permission
        if (! auth()->user()->hasRole('Owner') && ! auth()->user()->can('settings.manage')) {
            abort(403, 'Unauthorized access to audit logs.');
        }

        $query = AuditLog::with('user:id,name,email')
            ->latest('id');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('auditable_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(25)->through(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'event' => $log->event,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_type_full' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ];
        });

        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        $distinctEvents = AuditLog::select('event')->distinct()->pluck('event');

        return Inertia::render('settings/AuditLogs', [
            'logs' => $logs,
            'users' => $users,
            'distinctEvents' => $distinctEvents,
            'filters' => $request->only(['search', 'event', 'user_id', 'date_from', 'date_to']),
        ]);
    }
}
