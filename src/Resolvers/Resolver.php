<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Models\Field;

interface Resolver
{
    public function __construct(Field $field);

    /** @return array<array-key, mixed> */
    public function toStorable(Request $request, ?Model $sender = null): array;

    public function fromStorage(?Model $sender = null): mixed;
}
