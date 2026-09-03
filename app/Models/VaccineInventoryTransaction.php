<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaccineInventoryTransaction extends Model
{
    use UsesUuidPrimaryKey;

    public const TYPES = [
        'receipt' => 'Stock receipt',
        'usage' => 'Vaccination usage',
        'expired' => 'Expired stock',
        'damaged' => 'Damaged stock',
        'adjustment' => 'Stock adjustment',
    ];

    protected $fillable = [
        'barangay_id',
        'vaccine_type_id',
        'vaccine_inventory_item_id',
        'recorded_by',
        'transaction_type',
        'movement',
        'quantity',
        'batch_number',
        'expiry_date',
        'transaction_date',
        'reference_number',
        'notes',
        'sync_uuid',
        'sync_version',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expiry_date' => 'date',
            'transaction_date' => 'date',
            'sync_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->sync_uuid ??= (string) \Illuminate\Support\Str::uuid();
            $transaction->sync_version ??= 1;
        });
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function vaccineType(): BelongsTo
    {
        return $this->belongsTo(VaccineType::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(VaccineInventoryItem::class, 'vaccine_inventory_item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isMunicipalAdmin(),
            fn (Builder $builder) => $builder->whereIn('barangay_id', $user->accessibleBarangayIds())
        )->when(
            ! $user->isSuperAdmin() && ! $user->isMunicipalAdmin(),
            fn (Builder $builder) => $builder->where('barangay_id', $user->barangay_id)
        );
    }

    public function signedQuantity(): int
    {
        return $this->movement === 'out' ? -$this->quantity : $this->quantity;
    }

    public static function typeOptions(): array
    {
        return self::TYPES;
    }
}
