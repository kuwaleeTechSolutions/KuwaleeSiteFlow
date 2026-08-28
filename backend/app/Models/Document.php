<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Document extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'project_id', 'site_id', 'category', 'title',
        'description', 'confidentiality_level', 'disk', 'disk_path',
        'original_filename', 'mime_type', 'size', 'expiry_date', 'uploaded_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $document->uuid ??= (string) Str::uuid();
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_shares')->withPivot('shared_by')->withTimestamps();
    }

    public function isSharedWith(int $userId): bool
    {
        return $this->sharedWith()->where('users.id', $userId)->exists();
    }
}
