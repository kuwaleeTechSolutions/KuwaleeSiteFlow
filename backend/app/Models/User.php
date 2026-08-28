<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'email', 'password', 'phone',
        'avatar_path', 'status', 'is_super_admin',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * In-request cached permission set to avoid re-querying the
     * roles→permissions chain on every hasPermission() call within the same
     * request lifecycle.
     */
    protected ?array $permissionCache = null;

    public function permissionNames(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        return $this->permissionCache = $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return in_array($permission, $this->permissionNames(), true);
    }

    /**
     * True if ANY of the user's roles is flagged org_wide_visibility (e.g.
     * Owner/Admin) — bypasses the per-project/per-site assignment check in
     * Policies, but NEVER bypasses the permission check itself.
     */
    public function hasOrgWideVisibility(): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->roles()->where('org_wide_visibility', true)->exists();
    }

    public function isAssignedToProject(int $projectId): bool
    {
        return $this->relationLoaded('projects')
            ? $this->projects->contains('id', $projectId)
            : $this->belongsToMany(\App\Models\Project::class, 'project_users')
                ->where('projects.id', $projectId)->exists();
    }

    public function isAssignedToSite(int $siteId): bool
    {
        return $this->belongsToMany(\App\Models\Site::class, 'site_users')
            ->where('sites.id', $siteId)->exists();
    }
}
