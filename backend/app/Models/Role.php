<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'description', 'is_system', 'org_wide_visibility',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'org_wide_visibility' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Role $role) {
            $role->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * System role templates have organization_id = null and are cloned into
     * a concrete, organization-owned role when the organization is created
     * or when an admin resets roles to defaults. They are NEVER assignable
     * to a user directly.
     */
    public function isTemplate(): bool
    {
        return $this->organization_id === null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }
}
