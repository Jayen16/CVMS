<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use Archivable, UsesUuidPrimaryKey;

    protected $fillable = ['user_id', 'format', 'status', 'total_items', 'processed_items', 'path', 'error', 'filters', 'archived_at', 'archived_by', 'archive_reason'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'archived_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progress(): int
    {
        return $this->total_items > 0 ? (int) floor(($this->processed_items / $this->total_items) * 100) : 0;
    }
}
