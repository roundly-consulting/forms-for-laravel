<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Tests\testable\SwappedModelsTestCase;
use RoundlyConsulting\Forms\Tests\testable\UlidKeyTestCase;
use RoundlyConsulting\Forms\Tests\testable\UuidKeyTestCase;
use RoundlyConsulting\Forms\Tests\TestCase;

/**
 * Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different base
 * case, and a blanket bind claims it first — Pest binds a test case per directory, not per
 * file, and errors out ("the folder already uses the test case") rather than picking the
 * more specific one.
 *
 * ArchTest.php is named individually (`->in()` accepts a file path) because it needs the app
 * booted — `swappableModelsAreNotFinal` reads the `forms.models.*` config defaults — and an
 * arch file is not automatically test-cased.
 */
uses(TestCase::class)->in(
    __DIR__.'/ArchTest.php',
    __DIR__.'/Feature',
    __DIR__.'/src',
    __DIR__.'/KeyTypes/BigIntSenderTest.php',
);

/**
 * The sender key type is fixed at migrate time, so each non-default leg needs `forms.key_type`
 * set before the providers boot — a base case per key type is the only way to reach that
 * window. The bigint leg above rides the default base case because it must prove the
 * *unconfigured* install.
 */
uses(UuidKeyTestCase::class)->in(__DIR__.'/KeyTypes/UuidSenderTest.php');
uses(UlidKeyTestCase::class)->in(__DIR__.'/KeyTypes/UlidSenderTest.php');

/**
 * The model-swap proofs need every `forms.models.*` key pointed at a host subclass BEFORE
 * the providers boot, so they run on their own base case in their own directory.
 */
uses(SwappedModelsTestCase::class)->in(__DIR__.'/ModelSwap');
