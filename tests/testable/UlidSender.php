<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Forms\Concerns\HasForms;

/**
 * A sender keyed by a ULID, for hosts that set `forms.key_type` to `ulid`.
 *
 * @property string $id
 */
class UlidSender extends Model
{
    use HasForms;
    use HasUlids;

    protected $guarded = [];

    protected $table = 'ulid_senders';
}
