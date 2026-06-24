<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Forms\Models\Form;

/** @mixin Form */
class FormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'expires_at' => $this->expires_at?->toDateTimeString(),
            'is_public' => $this->is_public,
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
        ];
    }
}
