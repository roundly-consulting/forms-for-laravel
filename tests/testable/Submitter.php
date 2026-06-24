<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Forms\Concerns\HasForms;

/**
 * @property int $id
 */
class Submitter extends Model
{
    use HasForms;

    protected $guarded = [];

    protected $table = 'submitters';
}
