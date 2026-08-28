<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Append-only ledger. Nothing in this codebase ever calls ->update() or
 * ->delete() on a MaterialTransaction — corrections are new rows with
 * transaction_type='adjustment' and reversal_of_id pointing back to the
 * original (brief §16: "Maintain immutable stock movement history").
 */
class MaterialTransaction extends Model
{
    use BelongsToOrganization;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'material_id', 'transaction_type', 'quantity',
        'direction', 'project_id', 'site_id', 'to_site_id', 'reference_number',
        'remarks', 'is_override', 'reversal_of_id', 'created_by',
        // 'created_at' is fillable ONLY to support controlled historical
        // backdating (e.g. data import scripts, test fixtures establishing
        // trailing consumption history for MaterialAlertService). This is
        // safe because MaterialStockService — the ONLY code path that
        // creates these rows from user input — always builds an explicit
        // attribute whitelist and never forwards raw client input, so a
        // client can never supply created_at through the public API.
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'is_override' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MaterialTransaction $transaction) {
            $transaction->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * True if this transaction DECREASES the balance at its own `site_id`
     * location. inward/return always increase; issue/transfer(-out) always
     * decrease; adjustment relies on the explicit `direction` column since
     * a correction can legitimately go either way.
     */
    public function decreasesStock(): bool
    {
        if ($this->transaction_type === 'adjustment') {
            return $this->direction === 'decrease';
        }

        return in_array($this->transaction_type, ['issue', 'transfer'], true);
    }
}
