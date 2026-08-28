<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Site extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_id', 'site_code', 'site_name', 'location',
        'latitude', 'longitude', 'site_manager_id', 'status',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            $site->uuid ??= (string) Str::uuid();

            // The site's organization_id MUST match its parent project's
            // organization — never trust a client-supplied organization_id
            // for a site. This re-verification is layered on top of (not a
            // replacement for) the BelongsToOrganization stamping/scope.
            if ($site->project_id) {
                $project = Project::withoutGlobalScopes()->find($site->project_id);
                abort_if(! $project, 422, 'The selected project does not exist.');
                abort_unless(
                    (int) $project->organization_id === (int) $site->organization_id,
                    403,
                    'Site organization must match its parent project organization.'
                );
            }
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

    public function siteManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'site_manager_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_users')->withTimestamps();
    }

    public function isUserAssigned(int $userId): bool
    {
        return $this->assignedUsers()->where('users.id', $userId)->exists();
    }
}
