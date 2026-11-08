<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anota en cada registro la sucursal donde se creó (`branch_id`), tomada de BranchContext salvo que se
 * indique otra. Es solo una anotación: los datos clínicos son compartidos entre sucursales y por eso no
 * se filtran con un scope global.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->branch_id)) {
                $model->branch_id = BranchContext::currentId();
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Registros anotados en una sucursal determinada. */
    public function scopeInBranch(Builder $query, Branch|int $branch): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('branch_id'),
            $branch instanceof Branch ? $branch->id : $branch,
        );
    }
}
