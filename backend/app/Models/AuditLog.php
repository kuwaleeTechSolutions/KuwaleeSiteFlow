<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Deliberately has no `updated_at` column and no
 * update/delete affordances exposed anywhere in the application — audit
 * integrity depends on records never being mutated after creation.
 */
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'user_id', 'action', 'entity_type', 'entity_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Guard against accidental mutation from application code. Deletion is
     * still possible directly at the database layer for legally-mandated
     * retention policy purges, but never through the Eloquent model/API.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Audit logs are append-only and cannot be updated.');
    }
}
