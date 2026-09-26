<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Tests\testable\UlidSender;

require_once __DIR__.'/SenderKeyScenarios.php';

// `forms.key_type` = ulid (set before boot by UlidKeyTestCase), a ulid-keyed sender.
senderKeyTypeScenarios(UlidSender::class);
