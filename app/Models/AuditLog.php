<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event', 'auditable_type', 'auditable_id', 'description', 'old_values', 'new_values', 'url', 'ip_address', 'user_agent'])]
#[Hidden(['user_agent'])]
class AuditLog extends Model
{
    use UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /** @param array<string, mixed> $details @param array<string, mixed> $oldValues */
    public static function recordAction(string $event, string $description, ?EloquentModel $target = null, array $details = [], array $oldValues = []): void
    {
        $request = app()->bound('request') ? request() : null;

        self::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $target?->getMorphClass() ?? self::class,
            'auditable_id' => $target ? (string) $target->getKey() : null,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $details,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actorName(): string
    {
        return $this->user?->name ?? 'System';
    }

    public function targetName(): string
    {
        return str(class_basename($this->auditable_type))->headline();
    }
}
