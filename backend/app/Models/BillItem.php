<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'bill_id', 'measurement_item_id', 'boq_item_id', 'quantity_billed', 'rate', 'amount',
    ];

    protected $casts = [
        'quantity_billed' => 'decimal:3',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function measurementItem(): BelongsTo
    {
        return $this->belongsTo(MeasurementItem::class);
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }
}
