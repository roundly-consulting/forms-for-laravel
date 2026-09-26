<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;

/**
 * The sender round trip, run once per `forms.key_type` (see the three *SenderTest.php files,
 * each on a base case that migrates under its key type). The sender is polymorphic, so its id
 * is whatever the host keys senders by: every write must store that key as-is and every read
 * must find the sender by it. An integer cast stores a uuid/ulid sender as `0` or a digit
 * prefix (`(int) '0199…'` is `199`) — the rows then belong to nobody, and a strict engine
 * rejects the write outright.
 *
 * Not a *Test.php file, so Pest only loads it through the files that call it.
 *
 * @param  class-string<Model>  $senderClass
 */
function senderKeyTypeScenarios(string $senderClass): void
{
    $form = function (): Form {
        Forms::define('kt', 'Key types')
            ->public()
            ->group('g', 'G', function (GroupBuilder $g): void {
                $g->field('name', 'Name');
                $g->field('color', 'Color');
            })
            ->create();

        return Forms::find('kt');
    };

    $request = fn (array $values): Request => Request::create('t', parameters: ['kt' => ['g' => $values]]);

    it('stores the sender key on the aggregate and every field row', function () use ($senderClass, $form, $request): void {
        $sender = $senderClass::query()->create();

        $sender->submitTo($form(), $request(['name' => 'Ann', 'color' => 'red']));

        $aggregate = FormSubmission::query()->sole();

        expect($aggregate->sender_id)->toBe($sender->getKey())
            ->and($aggregate->sender?->is($sender))->toBeTrue()
            ->and(Submission::query()->pluck('sender_id')->unique()->values()->all())->toBe([$sender->getKey()])
            ->and($sender->formSubmissions()->count())->toBe(2)
            ->and($sender->formSubmissions()->first()?->sender?->is($sender))->toBeTrue();
    });

    it('reads a sender\'s submissions back by the sender', function () use ($senderClass, $form, $request): void {
        $form = $form();
        $ann = $senderClass::query()->create();
        $bob = $senderClass::query()->create();

        Forms::submit($form, $request(['name' => 'Ann', 'color' => 'red']), $ann);
        Forms::submit($form, $request(['name' => 'Bob', 'color' => 'blue']), $bob);

        $answers = Forms::submissions($form)->forSender($ann)->get();

        expect($answers)->toHaveCount(1)
            ->and($answers->first()?->senderId)->toBe($ann->getKey())
            ->and($answers->first()?->senderType)->toBe($ann->getMorphClass())
            ->and($answers->first()?->value('name'))->toBe('Ann');
    });

    it('resolves a stored value per sender', function () use ($senderClass, $form, $request): void {
        $form = $form();
        $ann = $senderClass::query()->create();
        $bob = $senderClass::query()->create();

        Forms::submit($form, $request(['name' => 'Ann', 'color' => 'red']), $ann);
        Forms::submit($form, $request(['name' => 'Bob', 'color' => 'blue']), $bob);

        $field = Field::query()->where('key', 'name')->sole();
        /** @var DefaultResolver $resolver */
        $resolver = $field->resolver();

        $loaded = Field::query()->where('key', 'name')->with('submissions')->sole();
        /** @var DefaultResolver $eager */
        $eager = $loaded->resolver();

        expect($resolver->fromStorage($ann))->toBe('Ann')
            ->and($resolver->fromStorage($bob))->toBe('Bob')
            ->and($eager->fromStorage($ann))->toBe('Ann')
            ->and($eager->fromStorage($bob))->toBe('Bob');
    });

    it('drafts, resumes and finalizes as the sender', function () use ($senderClass, $form, $request): void {
        $form = $form();
        $sender = $senderClass::query()->create();

        $draft = $sender->draftTo($form, $request(['name' => 'An']));
        Forms::draft($form, $request(['name' => 'Ann', 'color' => 'red']), $sender, uuid: $draft->uuid);
        Forms::finalize($draft->uuid);

        $aggregate = FormSubmission::query()->sole();

        expect($aggregate->sender_id)->toBe($sender->getKey())
            ->and($aggregate->isDraft())->toBeFalse()
            ->and($sender->formSubmissions()->count())->toBe(2)
            ->and(Forms::submissions($form)->forSender($sender)->first()?->value('name'))->toBe('Ann');
    });

    it('creates a single field row for the sender', function () use ($senderClass, $form): void {
        $form();
        $sender = $senderClass::query()->create();
        $field = Field::query()->where('key', 'name')->sole();

        $row = Forms::createSubmission($field, ['value' => 'Ann'], $sender);

        expect($row->fresh()?->sender_id)->toBe($sender->getKey())
            ->and($sender->formSubmissions()->count())->toBe(1);
    });
}
