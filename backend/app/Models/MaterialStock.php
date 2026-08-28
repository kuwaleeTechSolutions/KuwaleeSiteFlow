<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A maintained CACHE of the current balance, not a source of truth in its
 * own right — the material_transactions ledger is authoritative. Every
 * write to this table happens exclusively inside MaterialStockService,
 * under a row lock, as part of committing a transaction.
 */
class MaterialStock extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'material_id', 'project_id', 'site_id', 'quantity_on_hand', 'updated_at',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'updated_at' => 'datetime',
    ];

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

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->material->minimum_stock;
    }
}
