<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Measurement extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_id', 'site_id', 'measurement_date',
        'remarks', 'created_by', 'revises_measurement_id',
    ];

    protected $casts = [
        'measurement_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Measurement $measurement) {
            $measurement->uuid ??= (string) Str::uuid();
            $measurement->status ??= 'draft';

            if ($measurement->site_id && $measurement->project_id) {
                $site = Site::withoutGlobalScopes()->find($measurement->site_id);
                abort_unless(
                    $site && (int) $site->project_id === (int) $measurement->project_id,
                    422,
                    'The selected site does not belong to the selected project.'
                );
            }
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revisedMeasurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class, 'revises_measurement_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeasurementItem::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft'], true);
    }

    /**
     * True if ANY of this measurement's items are referenced by a bill_item
     * on a non-cancelled bill — the point at which brief §20 requires
     * blocking further edits entirely (a revision measurement must be
     * created instead).
     */
    public function isReferencedByABill(): bool
    {
        return $this->items()
            ->whereHas('billItems', fn ($q) => $q->whereHas('bill', fn ($qq) => $qq->where('status', '!=', 'cancelled')))
            ->exists();
    }
}
