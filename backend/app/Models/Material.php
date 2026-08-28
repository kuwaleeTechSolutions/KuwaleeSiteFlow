<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Material extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'material_code', 'material_name', 'category',
        'unit', 'minimum_stock', 'status',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:3',
    ];

    protected static function booted(): void
    {
        static::creating(function (Material $material) {
            $material->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MaterialStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MaterialTransaction::class);
    }
}
