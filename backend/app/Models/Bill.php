<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Bill extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_id', 'bill_number', 'bill_type',
        'bill_date', 'billing_period_start', 'billing_period_end',
        'previous_certified_amount', 'current_work_value', 'deductions',
        'taxes', 'net_payable', 'created_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'previous_certified_amount' => 'decimal:2',
        'current_work_value' => 'decimal:2',
        'deductions' => 'decimal:2',
        'taxes' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'certified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Bill $bill) {
            $bill->uuid ??= (string) Str::uuid();
            $bill->status ??= 'draft';
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft'], true);
    }

    public function isCertified(): bool
    {
        return ! in_array($this->status, ['draft', 'submitted'], true);
    }

    /**
     * paid_amount / remaining_amount / outstanding are ALWAYS computed live
     * from the payments ledger, never stored as independently-editable
     * fields — this is what prevents drift between the two (brief §22).
     */
    public function paidAmount(): string
    {
        // Deliberately NOT using ->sum('amount') here — Eloquent's sum()
        // coerces through PHP's native numeric types, which can reintroduce
        // float rounding error for money. Iterating with bcadd keeps exact
        // decimal precision end-to-end.
        return $this->payments()->pluck('amount')->reduce(
            fn (string $carry, $amount) => bcadd($carry, (string) $amount, 2),
            '0'
        );
    }

    public function outstandingAmount(): string
    {
        return bcsub((string) $this->net_payable, $this->paidAmount(), 2);
    }
}
