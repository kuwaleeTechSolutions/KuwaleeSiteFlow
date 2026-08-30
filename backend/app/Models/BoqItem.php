<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Immutable once created — a revision to an item's quantity/rate always
 * creates a NEW row (new boq_revision_id) rather than editing this one.
 * See BoqItemService for the "current effective" resolution logic.
 */
class BoqItem extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'project_id', 'boq_revision_id', 'item_number',
        'description', 'unit', 'contract_quantity', 'contract_rate', 'contract_value',
    ];

    protected $casts = [
        'contract_quantity' => 'decimal:3',
        'contract_rate' => 'decimal:2',
        'contract_value' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (BoqItem $item) {
            $item->uuid ??= (string) Str::uuid();
        });

        // Defense in depth: prevent accidental in-place edits from ANY
        // code path other than intentional creation of a new revision row.
        static::updating(function (BoqItem $item) {
            throw new \LogicException('BOQ items are immutable once created. Create a new revision instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(BoqRevision::class, 'boq_revision_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function measurementItems(): HasMany
    {
        return $this->hasMany(MeasurementItem::class);
    }
}
