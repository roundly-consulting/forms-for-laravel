<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Forms\Support\ReleasesTrashedKeys;

/**
 * Frees a soft-deleted record's `key` for reuse.
 *
 * A form's key is unique, a group's per form and a field's per group — among LIVE rows. A
 * unique index cannot tell a trashed row from a live one on every engine (MySQL has no
 * partial index), so each index also covers `deleted_token`: `0` while the row is live, the
 * row's own id once it is trashed. Live keys stay unique; any number of trashed rows may
 * keep the key they had. Restoring a row takes its key back — and is refused by the index
 * while a live row holds it.
 *
 * Both paths are covered: a model's `delete()` / `restore()` and a query's
 * `->delete()` / `->restore()`. Use it after {@see SoftDeletes}.
 *
 * @internal
 *
 * @mixin Model
 */
trait ReleasesKeyWhenTrashed
{
    public static function bootReleasesKeyWhenTrashed(): void
    {
        static::registerModelEvent('trashed', static function (Model $model): void {
            $model->newModelQuery()
                ->whereKey($model->getKey())
                ->toBase()
                ->update(['deleted_token' => $model->getKey()]);

            $model->setAttribute('deleted_token', $model->getKey());
            $model->syncOriginalAttribute('deleted_token');
        });

        static::registerModelEvent('restoring', static function (Model $model): void {
            $model->setAttribute('deleted_token', 0);
        });

        static::addGlobalScope(new ReleasesTrashedKeys);
    }
}
