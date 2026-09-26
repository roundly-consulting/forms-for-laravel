<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Forms\Concerns\HasForms;

/**
 * A sender keyed by a UUID, for hosts that set `forms.key_type` to `uuid`.
 *
 * @property string $id
 */
class UuidSender extends Model
{
    use HasForms;
    use HasUuids;

    protected $guarded = [];

    protected $table = 'uuid_senders';
}
