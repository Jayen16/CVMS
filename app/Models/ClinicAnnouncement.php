<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ClinicAnnouncement extends Model
{
    protected $fillable = [
        'barangay_id',
        'created_by',
        'title',
        'category',
        'audience',
        'starts_on',
        'ends_on',
        'location',
        'message',
        'active',
        'sync_uuid',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::creating(function (ClinicAnnouncement $announcement): void {
            if (blank($announcement->sync_uuid)) {
                $announcement->sync_uuid = (string) Str::uuid();
            }
        });
    }
}
