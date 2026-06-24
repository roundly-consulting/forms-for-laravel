<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Forms\Models\Field;

/** @mixin Field */
class FieldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'help' => $this->help,
            'type' => $this->type,
            'autofill' => [
                'enabled' => filled($this->autofill),
                'value' => $this->getAutofillValue(),
            ],
            'options' => [
                'has_options' => $this->hasOptions(),
                'values' => $this->options,
            ],
            'validations' => [
                'has_validations' => $this->hasValidations(),
                'validations' => $this->validations,
            ],
            'order' => $this->order,
        ];
    }
}
