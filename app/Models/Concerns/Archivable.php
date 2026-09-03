<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Archivable
{
    public static function bootArchivable(): void
    {
        static::addGlobalScope('not_archived', fn (Builder $query) => $query->whereNull($query->getModel()->qualifyColumn('archived_at')));
    }

    /** @param Builder<self> $query */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->withoutGlobalScope('not_archived')->whereNotNull($query->getModel()->qualifyColumn('archived_at'));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
