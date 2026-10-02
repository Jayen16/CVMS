<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event', 'auditable_type', 'auditable_id', 'description', 'old_values', 'new_values', 'url', 'ip_address', 'user_agent', 'archived_at', 'archived_by', 'archive_reason'])]
#[Hidden(['user_agent'])]
class AuditLog extends Model
{
    use Archivable, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    /** @param array<string, mixed> $details @param array<string, mixed> $oldValues */
    public static function recordAction(string $event, string $description, ?EloquentModel $target = null, array $details = [], array $oldValues = []): void
    {
        $request = app()->bound('request') ? request() : null;
        $actorId = auth()->id();

        // Some guest flows can retain a stale authenticated user ID after the
        // user record has been removed. Do not let audit logging break the
        // underlying operation when the nullable foreign key cannot resolve.
        $auditConnection = self::query()->getConnection();
        if ($actorId !== null && ! $auditConnection->table('users')->where('id', $actorId)->exists()) {
            $actorId = null;
        }

        try {
            $audit = self::query()->create([
                'user_id' => $actorId,
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
            app(\App\Services\OfflineSyncService::class)->queueAudit($audit);
        } catch (\Throwable $exception) {
            // Auditing must never turn a successful business operation into a
            // server error, especially during guest account activation.
            report($exception);
        }
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
