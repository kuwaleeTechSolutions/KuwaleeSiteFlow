<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MeasurementItem extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'measurement_id', 'boq_item_id', 'previous_quantity',
        'current_quantity', 'cumulative_quantity', 'unit', 'remarks',
    ];

    protected $casts = [
        'previous_quantity' => 'decimal:3',
        'current_quantity' => 'decimal:3',
        'cumulative_quantity' => 'decimal:3',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MeasurementItem $item) {
            $item->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }
}
