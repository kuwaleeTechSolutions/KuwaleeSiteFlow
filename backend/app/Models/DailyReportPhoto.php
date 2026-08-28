<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DailyReportPhoto extends Model
{
    use BelongsToOrganization;

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'daily_report_id', 'disk', 'disk_path',
        'original_filename', 'mime_type', 'size', 'caption', 'exif_stripped',
        'uploaded_by',
    ];

    protected $casts = [
        'exif_stripped' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DailyReportPhoto $photo) {
            $photo->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
