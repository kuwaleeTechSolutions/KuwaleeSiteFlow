<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_code', 'project_name', 'client_name',
        'contract_number', 'contract_value', 'start_date', 'expected_end_date',
        'actual_end_date', 'status', 'project_manager_id', 'description',
    ];

    protected $casts = [
        'contract_value' => 'decimal:2',
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'actual_end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function boqRevisions(): HasMany
    {
        return $this->hasMany(BoqRevision::class);
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_users')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function isUserAssigned(int $userId): bool
    {
        return $this->assignedUsers()->where('users.id', $userId)->exists();
    }
}
