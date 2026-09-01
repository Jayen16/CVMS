<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = ['user_id', 'format', 'status', 'total_items', 'processed_items', 'path', 'error', 'filters'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
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
