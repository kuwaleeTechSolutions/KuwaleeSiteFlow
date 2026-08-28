<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ComplianceItem extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id', 'document_id', 'title', 'type', 'issue_date',
        'expiry_date', 'responsible_person_id', 'related_entity_type',
        'related_entity_id', 'status', 'last_alert_threshold_days', 'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (ComplianceItem $item) {
            $item->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
    }

    public function daysUntilExpiry(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expiry_date, false);
    }
}
