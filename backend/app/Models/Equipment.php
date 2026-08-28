<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Equipment extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'organization_id', 'equipment_code', 'equipment_name', 'type',
        'registration_number', 'assigned_project_id', 'assigned_site_id',
        'current_operator_id', 'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Equipment $equipment) {
            $equipment->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function assignedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'assigned_project_id');
    }

    public function assignedSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'assigned_site_id');
    }

    public function currentOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_operator_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(EquipmentUsageLog::class);
    }

    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }
}
