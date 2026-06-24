<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Forms\Models\Field;

final readonly class SubmissionData
{
    /** @param  array<array-key, mixed>  $value */
    public function __construct(
        public string $uuid,
        public ?int $senderId,
        public ?string $senderType,
        public int $formId,
        public int $groupId,
        public int $fieldId,
        public array $value,
    ) {}

    /** @param  array<array-key, mixed>  $value */
    public static function forField(Field $field, string $uuid, array $value, ?Model $sender = null): self
    {
        $senderKey = $sender?->getKey();

        return new self(
            uuid: $uuid,
            senderId: $senderKey === null ? null : (int) $senderKey,
            senderType: $sender?->getMorphClass(),
            formId: $field->form_id,
            groupId: $field->group_id,
            fieldId: $field->getKey(),
            value: $value,
        );
    }
}
