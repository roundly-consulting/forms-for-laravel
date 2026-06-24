<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Tests\testable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Resolvers\Resolver;

class CustomResolver implements Resolver
{
    public function __construct(Field $field) {}

    /** @return array<array-key, mixed> */
    public function toStorable(Request $request, ?Model $sender = null): array
    {
        return $request->all();
    }

    public function fromStorage(?Model $sender = null): mixed
    {
        return $sender?->getKey() ?? 'Unknown';
    }
}
