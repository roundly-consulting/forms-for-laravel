<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Enums;

use RoundlyConsulting\Enums\Helpers;

enum SubmissionStatus: string
{
    use Helpers;

    /**
     * A saved-but-not-yet-finalized submission. Only the storage lifecycle
     * uses this — a draft has not been submitted for real yet.
     */
    case Draft = 'draft';

    /**
     * A finalized submission. This is the terminal state when no approval
     * review is required (the historical default).
     */
    case Final = 'final';

    /**
     * A finalized submission that has been routed through the approvals engine
     * and is awaiting sign-off.
     */
    case Pending = 'pending';

    /**
     * The review resolved in favour of the submission.
     */
    case Approved = 'approved';

    /**
     * The review resolved against the submission.
     */
    case Rejected = 'rejected';

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Whether the submission's values are locked in — either plainly finalized
     * or accepted through review.
     */
    public function isFinalized(): bool
    {
        return $this === self::Final || $this === self::Approved;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::Pending;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    /**
     * Whether the submission is a finalized subject the approvals engine can act
     * on (everything except an unfinalized draft).
     */
    public function isReviewable(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * Whether the submission has reached an end state and should no longer move
     * on its own.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Final, self::Approved, self::Rejected => true,
            self::Draft, self::Pending => false,
        };
    }

    /**
     * Whether a move from this status to $to is allowed by the lifecycle graph.
     */
    public function canTransitionTo(self $to): bool
    {
        if ($this === $to) {
            return true;
        }

        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * The statuses this status may transition to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Final],
            self::Final => [self::Pending, self::Approved, self::Rejected],
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Pending, self::Rejected],
            self::Rejected => [self::Pending, self::Approved],
        };
    }
}
