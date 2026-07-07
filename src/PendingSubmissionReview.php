<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Forms\Actions\ReviewSubmissionAction;
use RoundlyConsulting\Forms\Models\FormSubmission;

/**
 * Fluent builder for routing a whole submission through the approvals engine:
 *
 * ```php
 * Forms::review($submission)
 *     ->requiring([$lead, $qa])
 *     ->quorum(2)
 *     ->open();
 * ```
 */
final class PendingSubmissionReview
{
    /** @var list<Model> */
    private array $approvers = [];

    private ApprovalRule $rule = ApprovalRule::Unanimous;

    private ?int $quorum = null;

    public function __construct(
        private readonly FormSubmission $submission,
        private readonly ReviewSubmissionAction $action = new ReviewSubmissionAction,
    ) {}

    /** @param  list<Model>  $approvers */
    public function requiring(array $approvers): self
    {
        $this->approvers = $approvers;

        return $this;
    }

    public function rule(ApprovalRule $rule): self
    {
        $this->rule = $rule;

        return $this;
    }

    public function unanimous(): self
    {
        return $this->rule(ApprovalRule::Unanimous);
    }

    public function any(): self
    {
        return $this->rule(ApprovalRule::Any);
    }

    public function quorum(int $quorum): self
    {
        $this->rule = ApprovalRule::Quorum;
        $this->quorum = $quorum;

        return $this;
    }

    public function weighted(int $threshold): self
    {
        $this->rule = ApprovalRule::Weighted;
        $this->quorum = $threshold;

        return $this;
    }

    public function open(): ApprovalRequest
    {
        return $this->action->execute($this->submission, $this->approvers, $this->rule, $this->quorum);
    }
}
