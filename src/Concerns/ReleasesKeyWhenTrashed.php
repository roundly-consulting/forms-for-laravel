<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
 * `->delete()` / `->restore()`, whichever order the model lists the traits in. Use it with
 * {@see SoftDeletes}.
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

        // A builder extends its scopes in registration order and keeps the last `onDelete` /
        // `restore` it is given, so ours must come after the soft-deleting scope whichever
        // trait boots first. Claim that scope's slot here: when SoftDeletes boots later it
        // re-registers under the same key, which keeps the slot's original position.
        static::addGlobalScope(new SoftDeletingScope);
        static::addGlobalScope(new ReleasesTrashedKeys);
    }
}
