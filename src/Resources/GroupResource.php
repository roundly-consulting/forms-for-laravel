<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Forms\Models\Group;

/** @mixin Group */
class GroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'order' => $this->order,
            'fields' => FieldResource::collection($this->whenLoaded('fields')),
        ];
    }
}
