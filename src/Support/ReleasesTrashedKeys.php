<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Expression;
use RoundlyConsulting\Forms\Concerns\ReleasesKeyWhenTrashed;

/**
 * The query-level half of {@see ReleasesKeyWhenTrashed}: a builder's soft `delete()` stamps
 * each row's `deleted_token` with its own id, and a builder's `restore()` clears it again.
 * It replaces the soft-deleting scope's own two callbacks, which set `deleted_at` only.
 *
 * @internal
 *
 * @implements Scope<Model>
 */
final class ReleasesTrashedKeys implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void {}

    /**
     * @param  Builder<*>  $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->onDelete(static function (Builder $builder): int {
            $model = $builder->getModel();
            $query = $builder->toBase();
            $now = $model->freshTimestampString();

            return $query->update([
                'deleted_at' => $now,
                $model->getUpdatedAtColumn() ?? 'updated_at' => $now,
                // Each row's own primary key: the package's tables are all keyed `id`.
                'deleted_token' => new Expression('id'),
            ]);
        });

        $builder->macro('restore', function (Builder $builder): int {
            return $builder
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->toBase()
                ->update([
                    'deleted_at' => null,
                    $builder->getModel()->getUpdatedAtColumn() ?? 'updated_at' => $builder->getModel()->freshTimestampString(),
                    'deleted_token' => 0,
                ]);
        });
    }
}
