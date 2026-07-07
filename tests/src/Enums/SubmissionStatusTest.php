<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Enums\SubmissionStatus;

it('adopts the enums Helpers trait', function () {
    expect(SubmissionStatus::values()->all())
        ->toBe(['draft', 'final', 'pending', 'approved', 'rejected'])
        ->and(SubmissionStatus::validationRule())->toBe('in:draft,final,pending,approved,rejected')
        ->and(SubmissionStatus::Final->readable())->toBe('Final')
        ->and(SubmissionStatus::options())->toHaveCount(5);
});

it('looks up cases by value and name', function () {
    expect(SubmissionStatus::from('approved'))->toBe(SubmissionStatus::Approved)
        ->and(SubmissionStatus::tryFrom('nope'))->toBeNull()
        ->and(SubmissionStatus::fromName('Rejected'))->toBe(SubmissionStatus::Rejected)
        ->and(SubmissionStatus::Draft->is(SubmissionStatus::Draft))->toBeTrue();
});

it('reports lifecycle predicates', function () {
    expect(SubmissionStatus::Draft->isDraft())->toBeTrue()
        ->and(SubmissionStatus::Draft->isReviewable())->toBeFalse()
        ->and(SubmissionStatus::Final->isReviewable())->toBeTrue()
        ->and(SubmissionStatus::Final->isFinalized())->toBeTrue()
        ->and(SubmissionStatus::Approved->isFinalized())->toBeTrue()
        ->and(SubmissionStatus::Pending->isPendingApproval())->toBeTrue()
        ->and(SubmissionStatus::Approved->isApproved())->toBeTrue()
        ->and(SubmissionStatus::Rejected->isRejected())->toBeTrue()
        ->and(SubmissionStatus::Pending->isFinalized())->toBeFalse();
});

it('reports terminal states', function () {
    expect(SubmissionStatus::Final->isTerminal())->toBeTrue()
        ->and(SubmissionStatus::Approved->isTerminal())->toBeTrue()
        ->and(SubmissionStatus::Rejected->isTerminal())->toBeTrue()
        ->and(SubmissionStatus::Draft->isTerminal())->toBeFalse()
        ->and(SubmissionStatus::Pending->isTerminal())->toBeFalse();
});

it('enforces the transition graph', function () {
    expect(SubmissionStatus::Draft->canTransitionTo(SubmissionStatus::Final))->toBeTrue()
        ->and(SubmissionStatus::Draft->canTransitionTo(SubmissionStatus::Pending))->toBeFalse()
        ->and(SubmissionStatus::Final->canTransitionTo(SubmissionStatus::Pending))->toBeTrue()
        ->and(SubmissionStatus::Pending->canTransitionTo(SubmissionStatus::Approved))->toBeTrue()
        ->and(SubmissionStatus::Pending->canTransitionTo(SubmissionStatus::Rejected))->toBeTrue()
        ->and(SubmissionStatus::Approved->canTransitionTo(SubmissionStatus::Approved))->toBeTrue()
        ->and(SubmissionStatus::Rejected->canTransitionTo(SubmissionStatus::Pending))->toBeTrue();
});
