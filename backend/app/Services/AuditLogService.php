<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Field names that must NEVER be persisted into old_values/new_values,
     * even if they appear on a model's attribute array. Applied defensively
     * regardless of the model's own $hidden configuration.
     */
    private const REDACTED_KEYS = [
        'password', 'remember_token', 'token', 'access_token', 'refresh_token',
        'secret', 'api_key', 'api_secret', 'otp',
    ];

    public function log(
        string $action,
        ?Model $entity = null,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $actor ??= auth()->user();
        $request = request();

        return AuditLog::create([
            'organization_id' => $actor?->organization_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->getKey(),
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function redact(?array $values): ?array
    {
        if (! $values) {
            return $values;
        }

        foreach (self::REDACTED_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
