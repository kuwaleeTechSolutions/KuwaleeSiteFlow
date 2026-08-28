<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Append-only — nothing in this codebase ever updates or deletes a
 * boq_revisions row. Each revision is a permanent, dated snapshot marker
 * that new boq_items rows attach to.
 */
class BoqRevision extends Model
{
    use BelongsToOrganization;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'project_id', 'revision_number', 'reason',
        'effective_date', 'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BoqRevision $revision) {
            $revision->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }
}
