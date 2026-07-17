<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use RoundlyConsulting\Forms\Tests\TestCase;

/**
 * The suite's base case with all five `forms.models.*` keys pointed at host subclasses
 * BEFORE the providers boot.
 *
 * Boot order is the whole point. The provider hangs its listeners (ApprovalRequestResolved
 * -> SyncSubmissionStatusFromApproval) and the models' media buckets on whatever these keys
 * name at boot. A `config()->set()` inside a test body reads back correctly but leaves every
 * listener on the packaged class — precisely the shape that let media #28 ship.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * the base case's media wiring — the same decapitation an un-parented `defineEnvironment()`
 * override causes one level up.
 *
 * @see TestCase
 */
abstract class SwappedModelsTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'forms.models.form' => CustomForm::class,
            'forms.models.group' => CustomGroup::class,
            'forms.models.field' => CustomField::class,
            'forms.models.submission' => CustomSubmission::class,
            'forms.models.form_submission' => CustomFormSubmission::class,
        ]);
    }
}
