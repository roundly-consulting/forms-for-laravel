<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Tests\testable\Submitter;

require_once __DIR__.'/SenderKeyScenarios.php';

// The unconfigured install: `forms.key_type` = bigint, an auto-increment sender.
senderKeyTypeScenarios(Submitter::class);
