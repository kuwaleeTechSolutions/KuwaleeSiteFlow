<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FuelTransaction extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'equipment_id', 'transaction_type', 'project_id',
        'site_id', 'opening_reading', 'closing_reading', 'quantity',
        'unit_cost', 'total_cost', 'recorded_by', 'reviewed_by',
        'reviewed_at', 'remarks',
        // See MaterialTransaction for why 'created_at' is deliberately
        // fillable — needed only for controlled test/seed backdating to
        // establish trailing consumption history; the public API never
        // forwards raw client input for this field.
        'created_at',
    ];

    protected $casts = [
        'opening_reading' => 'decimal:2',
        'closing_reading' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (FuelTransaction $transaction) {
            $transaction->uuid ??= (string) Str::uuid();

            if ($transaction->site_id && $transaction->project_id) {
                $site = Site::withoutGlobalScopes()->find($transaction->site_id);
                abort_unless(
                    $site && (int) $site->project_id === (int) $transaction->project_id,
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

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_by !== null;
    }

    public function hasMissingMeterReading(): bool
    {
        return $this->transaction_type === 'issue'
            && ($this->opening_reading === null || $this->closing_reading === null);
    }

    /**
     * Consumption rate = quantity / reading-delta (e.g. litres per km or
     * per hour, depending on the equipment's usage unit). Null when either
     * reading is missing or the delta is zero/negative (bad data).
     */
    public function consumptionRate(): ?string
    {
        if ($this->hasMissingMeterReading()) {
            return null;
        }

        $delta = bcsub((string) $this->closing_reading, (string) $this->opening_reading, 2);

        if (bccomp($delta, '0', 2) <= 0) {
            return null;
        }

        return bcdiv((string) $this->quantity, $delta, 4);
    }
}
