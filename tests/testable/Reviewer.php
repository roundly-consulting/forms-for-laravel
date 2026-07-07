<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

/**
 * @property int $id
 */
class Reviewer extends Model
{
    use GivesApprovals;

    protected $guarded = [];

    protected $table = 'reviewers';
}
