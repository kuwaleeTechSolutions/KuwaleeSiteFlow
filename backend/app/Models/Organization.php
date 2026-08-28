<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'legal_name', 'logo_path', 'email', 'phone', 'address',
        'city', 'state', 'country', 'gst_number', 'status', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            $organization->uuid ??= (string) Str::uuid();

            if (empty($organization->slug)) {
                $base = Str::slug($organization->name);
                $slug = $base;
                $suffix = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.(++$suffix);
                }
                $organization->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Configured currency/timezone/date-format/etc. Falls back to sane
     * India-centric defaults for the pilot deployment (Assam, India based
     * contractor organizations).
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
