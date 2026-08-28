<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Worker extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'worker_code', 'name', 'phone', 'address', 'trade',
        'skill_category', 'daily_wage', 'joining_date', 'status', 'photo_path',
    ];

    protected $casts = [
        'daily_wage' => 'decimal:2',
        'joining_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Worker $worker) {
            $worker->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(WorkerAttendance::class);
    }

    public function wageComputations(): HasMany
    {
        return $this->hasMany(WageComputation::class);
    }
}
