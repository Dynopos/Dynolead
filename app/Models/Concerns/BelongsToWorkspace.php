<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rows belong to one workspace. While a workspace is current, queries only
 * see its rows and new rows are stamped with its id.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $query) {
            $id = app(CurrentWorkspace::class)->id();

            if ($id !== null) {
                $query->where($query->getModel()->qualifyColumn('workspace_id'), $id);
            }
        });

        static::creating(function ($model) {
            $model->workspace_id ??= app(CurrentWorkspace::class)->id();
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
