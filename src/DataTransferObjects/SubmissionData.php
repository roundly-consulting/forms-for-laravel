<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Forms\Enums\SubmissionStatus;
use RoundlyConsulting\Forms\Models\Field;

final readonly class SubmissionData
{
    /** @param  array<array-key, mixed>  $value */
    public function __construct(
        public string $uuid,
        public int|string|null $senderId,
        public ?string $senderType,
        public int $formId,
        public int $groupId,
        public int $fieldId,
        public array $value,
        public ?SubmissionStatus $status = null,
        public ?int $formSubmissionId = null,
    ) {}

    /** @param  array<array-key, mixed>  $value */
    public static function forField(
        Field $field,
        string $uuid,
        array $value,
        ?Model $sender = null,
        ?SubmissionStatus $status = null,
        ?int $formSubmissionId = null,
    ): self {
        return new self(
            uuid: $uuid,
            senderId: self::senderKey($sender),
            senderType: $sender?->getMorphClass(),
            formId: $field->form_id,
            groupId: $field->group_id,
            fieldId: $field->getKey(),
            value: $value,
            status: $status,
            formSubmissionId: $formSubmissionId,
        );
    }

    /**
     * The sender's key exactly as the sender returns it. `forms.key_type` lets a host key its
     * senders by uuid/ulid; an integer cast would store those as `0` or a digit prefix.
     */
    public static function senderKey(?Model $sender): int|string|null
    {
        $key = $sender?->getKey();

        return $key === null || is_int($key) ? $key : (string) $key;
    }
}
