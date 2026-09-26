<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Tests\testable\UuidSender;

require_once __DIR__.'/SenderKeyScenarios.php';

// `forms.key_type` = uuid (set before boot by UuidKeyTestCase), a uuid-keyed sender.
senderKeyTypeScenarios(UuidSender::class);
