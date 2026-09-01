<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changed = $model->getChanges();
        unset($changed['updated_at']);

        if ($changed === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changed) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        $this->record($model, 'updated', $old, $changed);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), []);
    }

    /** @param array<string, mixed> $oldValues @param array<string, mixed> $newValues */
    private function record(Model $model, string $event, array $oldValues, array $newValues): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $redact = static function (array $values): array {
            foreach (['password', 'password_confirmation', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'token'] as $key) {
                if (array_key_exists($key, $values)) {
                    $values[$key] = '[REDACTED]';
                }
            }

            return $values;
        };

        AuditLog::recordAction(
            $event,
            str($event)->headline().' '.str(class_basename($model))->headline(),
            $model,
            $redact($newValues),
            $redact($oldValues),
        );
    }
}
