<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an append-only audit log entry.
     */
    public static function log(
        string $event,
        string|Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): AuditLog {
        $auditableType = is_string($auditable) ? $auditable : get_class($auditable);
        $auditableId = is_object($auditable) && isset($auditable->id) ? $auditable->id : null;

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
