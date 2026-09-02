<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaccineInventoryItem extends Model
{
    use UsesUuidPrimaryKey;

    protected $fillable = [
        'item_code',
        'barangay_id',
        'vaccine_type_id',
        'batch_number',
        'expiry_date',
        'received_at',
        'reference_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'date',
        ];
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function vaccineType(): BelongsTo
    {
        return $this->belongsTo(VaccineType::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VaccineInventoryTransaction::class);
    }

    public function availableStock(): int
    {
        return (int) ($this->stock_in ?? $this->transactions()->where('movement', 'in')->sum('quantity'))
            - (int) ($this->stock_out ?? $this->transactions()->where('movement', 'out')->sum('quantity'));
    }

    public function scopeForBarangay(Builder $query, string $barangayId): Builder
    {
        return $query->where('barangay_id', $barangayId);
    }
}
