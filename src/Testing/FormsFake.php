<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Forms\DataTransferObjects\FormDefinitionData;
use RoundlyConsulting\Forms\DataTransferObjects\SubmissionResult;
use RoundlyConsulting\Forms\FormBuilder;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Services\FormsService;
use RoundlyConsulting\Forms\UpdateFormBuilder;

/**
 * A recording, still-performing variant of {@see FormsService} for host-app
 * tests. Operations run against the database as usual while the fake records
 * intent so assertions can verify it — matching Laravel's `*::fake()`
 * ergonomics (see {@see FormsService::fake()}).
 *
 * It lives in src/ so host apps can use it; it depends only on PHPUnit's
 * Assert, which is always present in a Laravel app's dev dependencies.
 */
final class FormsFake extends FormsService
{
    /** @var list<array{key: string, name: string}> */
    private array $defined = [];

    /** @var list<Form> */
    private array $created = [];

    /** @var list<string> */
    private array $updated = [];

    /** @var list<array{form: Form, result: SubmissionResult, sender: ?Model}> */
    private array $submitted = [];

    /** @var list<array{form: Form, result: SubmissionResult, sender: ?Model}> */
    private array $drafted = [];

    /** @var list<array{uuid: string, result: SubmissionResult}> */
    private array $finalized = [];

    /** @var list<list<string>> */
    private array $synced = [];

    /** @var list<Submission> */
    private array $createdSubmissions = [];

    public function define(string $key, string $name): FormBuilder
    {
        $this->defined[] = ['key' => $key, 'name' => $name];

        return parent::define($key, $name);
    }

    public function create(FormDefinitionData $data): Form
    {
        $form = parent::create($data);

        $this->created[] = $form;

        return $form;
    }

    public function update(string $key): UpdateFormBuilder
    {
        $this->updated[] = $key;

        return parent::update($key);
    }

    public function submit(Form $form, Request $request, ?Model $sender = null, bool $bypassClosed = false): SubmissionResult
    {
        $result = parent::submit($form, $request, $sender, $bypassClosed);

        $this->submitted[] = ['form' => $form, 'result' => $result, 'sender' => $sender];

        return $result;
    }

    public function draft(Form $form, Request $request, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false): SubmissionResult
    {
        $result = parent::draft($form, $request, $sender, $uuid, $bypassClosed);

        $this->drafted[] = ['form' => $form, 'result' => $result, 'sender' => $sender];

        return $result;
    }

    public function finalize(string $uuid, bool $bypassClosed = false): SubmissionResult
    {
        $result = parent::finalize($uuid, $bypassClosed);

        $this->finalized[] = ['uuid' => $uuid, 'result' => $result];

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>|null  $definitions
     * @return list<string>
     */
    public function sync(?array $definitions = null): array
    {
        $keys = parent::sync($definitions);

        $this->synced[] = $keys;

        return $keys;
    }

    /** @param  array<array-key, mixed>  $value */
    public function createSubmission(Field $field, array $value, ?Model $sender = null, ?string $uuid = null, bool $bypassClosed = false): Submission
    {
        $submission = parent::createSubmission($field, $value, $sender, $uuid, $bypassClosed);

        $this->createdSubmissions[] = $submission;

        return $submission;
    }

    /**
     * Assert a submission was made — optionally for a specific form and/or
     * matching a callback that receives the {@see SubmissionResult} and {@see Form}.
     */
    public function assertSubmitted(?Form $form = null, ?callable $callback = null): void
    {
        $records = $this->recordsFor($this->submitted, $form);

        Assert::assertNotEmpty(
            $records,
            $form === null
                ? 'Expected a form submission, but none were recorded.'
                : "Expected a submission for form [{$form->key}], but none were recorded.",
        );

        if ($callback !== null) {
            Assert::assertTrue(
                $this->matchesSubmissions($records, $callback),
                'Expected a submission matching the callback, but none did.',
            );
        }
    }

    public function assertNotSubmitted(?Form $form = null): void
    {
        Assert::assertEmpty(
            $this->recordsFor($this->submitted, $form),
            $form === null
                ? 'Expected no form submissions, but some were recorded.'
                : "Expected no submission for form [{$form->key}], but one was recorded.",
        );
    }

    public function assertSubmittedCount(int $count): void
    {
        Assert::assertCount($count, $this->submitted, "Expected [{$count}] submissions.");
    }

    public function assertNothingSubmitted(): void
    {
        Assert::assertEmpty($this->submitted, 'Expected nothing to be submitted.');
    }

    public function assertDrafted(?Form $form = null, ?callable $callback = null): void
    {
        $records = $this->recordsFor($this->drafted, $form);

        Assert::assertNotEmpty(
            $records,
            $form === null
                ? 'Expected a draft submission, but none were recorded.'
                : "Expected a draft for form [{$form->key}], but none were recorded.",
        );

        if ($callback !== null) {
            Assert::assertTrue(
                $this->matchesSubmissions($records, $callback),
                'Expected a draft matching the callback, but none did.',
            );
        }
    }

    public function assertFinalized(?string $uuid = null): void
    {
        if ($uuid === null) {
            Assert::assertNotEmpty($this->finalized, 'Expected a submission to be finalized, but none were.');

            return;
        }

        Assert::assertContains(
            $uuid,
            array_column($this->finalized, 'uuid'),
            "Expected submission [{$uuid}] to be finalized, but it was not.",
        );
    }

    public function assertFormDefined(string $key): void
    {
        $keys = array_merge(
            array_column($this->defined, 'key'),
            array_map(static fn (Form $form): string => $form->key, $this->created),
        );

        Assert::assertContains($key, $keys, "Expected form [{$key}] to be defined, but it was not.");
    }

    public function assertFormCreated(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->created, 'Expected a form to be created, but none were.');

            return;
        }

        $matched = false;

        foreach ($this->created as $form) {
            if ($callback($form) === true) {
                $matched = true;

                break;
            }
        }

        Assert::assertTrue($matched, 'Expected a created form matching the callback, but none did.');
    }

    /**
     * Assert a submission row was created directly via createSubmission(),
     * optionally matching a callback that receives the {@see Submission}.
     */
    public function assertSubmissionCreated(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->createdSubmissions, 'Expected a submission to be created, but none were.');

            return;
        }

        $matched = false;

        foreach ($this->createdSubmissions as $submission) {
            if ($callback($submission) === true) {
                $matched = true;

                break;
            }
        }

        Assert::assertTrue($matched, 'Expected a created submission matching the callback, but none did.');
    }

    public function assertFormUpdated(string $key): void
    {
        Assert::assertContains($key, $this->updated, "Expected form [{$key}] to be updated, but it was not.");
    }

    public function assertSynced(): void
    {
        Assert::assertNotEmpty($this->synced, 'Expected forms to be synced, but sync() was never called.');
    }

    /**
     * @param  list<array{form: Form, result: SubmissionResult, sender: ?Model}>  $records
     * @return list<array{form: Form, result: SubmissionResult, sender: ?Model}>
     */
    private function recordsFor(array $records, ?Form $form): array
    {
        if ($form === null) {
            return $records;
        }

        return array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['form']->key === $form->key,
        ));
    }

    /**
     * @param  list<array{form: Form, result: SubmissionResult, sender: ?Model}>  $records
     */
    private function matchesSubmissions(array $records, callable $callback): bool
    {
        foreach ($records as $record) {
            if ($callback($record['result'], $record['form']) === true) {
                return true;
            }
        }

        return false;
    }
}
