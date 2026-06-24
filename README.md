<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/forms-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=forms-for-laravel">
    <img src="art/hero.png" alt="Forms for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Forms for Laravel

Define multi-step form structures (forms, groups, and fields) in your database, validate
user input against per-field rules, and store submissions — all driven by Eloquent models you
can swap for your own.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/forms-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="forms-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="forms-config"
```

Optionally publish the translations to customise the package's messages:

```bash
php artisan vendor:publish --tag="forms-translations"
```

## Configuration

The published `config/forms.php` lets you swap the package models for your own and register
custom field resolvers:

```php
return [
    // Override any model with your own subclass.
    'models' => [
        'form' => \RoundlyConsulting\Forms\Models\Form::class,
        'group' => \RoundlyConsulting\Forms\Models\Group::class,
        'field' => \RoundlyConsulting\Forms\Models\Field::class,
        'submission' => \RoundlyConsulting\Forms\Models\Submission::class,
    ],

    // Map a field `type` to the resolver that reads/writes its value.
    // `default` is used for any type without an explicit mapping.
    'fields' => [
        'default' => \RoundlyConsulting\Forms\Resolvers\DefaultResolver::class,
        'file' => \RoundlyConsulting\Forms\Resolvers\FileResolver::class,
    ],

    // Filesystem settings used by the FileResolver.
    'file' => [
        'disk' => env('FORMS_FILE_DISK', config('filesystems.default', 'local')),
        'directory' => env('FORMS_FILE_DIRECTORY', 'form-uploads'),
    ],

    // Forms defined declaratively and synced to the DB with `php artisan forms:sync`.
    'definitions' => [],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `models.form` | `class-string` | `Models\Form` | Model used for forms. |
| `models.group` | `class-string` | `Models\Group` | Model used for groups. |
| `models.field` | `class-string` | `Models\Field` | Model used for fields. |
| `models.submission` | `class-string` | `Models\Submission` | Model used for submissions. |
| `fields.default` | `class-string` | `Resolvers\DefaultResolver` | Resolver used for any field type without a specific mapping. |
| `fields.file` | `class-string` | `Resolvers\FileResolver` | Resolver for `file` fields; stores the upload on a disk. |
| `file.disk` | `string` | app default disk (`FORMS_FILE_DISK`) | Disk uploaded files are stored on. |
| `file.directory` | `string` | `form-uploads` (`FORMS_FILE_DIRECTORY`) | Directory within the disk. |
| `definitions` | `array` | `[]` | Declarative form definitions synced by `forms:sync`. |

## Usage

### Defining a form structure

A form has **groups** (think steps/sections), and each group has **fields**. Create them with
the models directly:

```php
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Field;

$form = Form::create([
    'name' => 'My Form',
    'key' => 'my-form',
    'is_public' => true,        // optional, defaults to false
    'expires_at' => null,       // optional nullable datetime
]);

$group = Group::create([
    'form_id' => $form->getKey(),
    'name' => 'First Step',
    'key' => 'first-step',
    'order' => 0,
]);

$field = Field::create([
    'form_id' => $form->getKey(),
    'group_id' => $group->getKey(),
    'name' => 'Name',
    'key' => 'name',
    'help' => 'Enter your name',
    'type' => 'text',
    'validations' => ['required', 'string'],
    'options' => null,          // e.g. ['CZ' => 'Czechia', 'SK' => 'Slovakia']
    'autofill' => null,
    'order' => 0,
]);
```

Each field exposes a dotted `path()` built from `form.key`, `group.key`, and `field.key`
(e.g. `my-form.first-step.name`) — this is the request key the resolver reads from.

### Defining a form with the fluent builder

The `Forms` facade exposes a fluent builder that defines a whole form — groups and fields —
in one transactional call, auto-assigning each `order` by declaration sequence:

```php
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;

$form = Forms::define('contact', 'Contact us')
    ->public()
    ->expiresAt(now()->addDays(30))
    ->group('details', 'Your details', function (GroupBuilder $g): void {
        $g->field('name', 'Name')->rules(['required', 'string'])->help('Full name');
        $g->field('email', 'Email')->type('email')->rules(['required', 'email']);
    })
    ->group('message', 'Message', function (GroupBuilder $g): void {
        $g->field('body', 'Message')->type('textarea')->rules(['required']);
    })
    ->create(); // returns the Form, persists the structure, dispatches FormCreated
```

Field builder methods: `type()`, `help()`, `autofill()`, `options()`, `rules()`, `order()`.
Override the auto-assigned order with `->order(n)` on a group or field.

#### Typed field shortcuts

Thin sugar over `type()`/`rules()`/`options()` for the common field kinds:

```php
$g->field('email', 'Email')->email()->required();
$g->field('bio', 'Bio')->textarea();
$g->field('terms', 'Terms')->checkbox();      // type=checkbox, rule=boolean
$g->field('age', 'Age')->number();            // type=number, rule=numeric
$g->field('dob', 'DOB')->date();              // type=date, rule=date
$g->field('role', 'Role')->select(['a' => 'A', 'b' => 'B']);
$g->field('avatar', 'Avatar')->file();        // type=file (see FileResolver)
```

#### Conditional fields

Show or require a field only when another field in the same form matches a value. The
condition is stored on the field and enforced during validation — a hidden field is skipped,
and a conditionally-required field is enforced only when its condition is met:

```php
$g->field('country', 'Country');
$g->field('state', 'State')->requiredWhen('country', 'US');
$g->field('guardian', 'Guardian')->visibleWhen('age', [16, 17], 'in');
```

Supported operators: `=` (default), `!=`, `in`, `not_in`, `filled`, `empty`.

#### Custom validation messages

Pass per-field messages as the second argument to `rules()` (or via `messages()`); they're
stored on the field and applied by the validator:

```php
$g->field('email', 'Email')->rules(['required', 'email'], [
    'required' => 'We really need your email.',
]);
```

### Working with forms via the `Forms` facade

The `Forms` facade resolves a form with its ordered groups and fields, validates request
input against each field's rules, and stores submissions:

```php
use RoundlyConsulting\Forms\Facades\Forms;

// Eager-loads groups and fields, ordered by their `order` column.
// Throws RoundlyConsulting\Forms\Exceptions\FormNotFoundException for an unknown key.
$form = Forms::find('contact');

// Validate request input — throws Illuminate\Validation\ValidationException on failure.
// Returns the validated data.
$validated = Forms::validate(form: $form, request: request());

// Persist a submission for every field. Returns a SubmissionResult.
$result = Forms::submit(
    form: $form,
    request: request(),
    sender: auth()->user(), // any Eloquent model, or null
);

$result->uuid;        // shared reference id for this submission
$result->fieldCount;  // number of fields stored
$result->submittedAt; // CarbonInterface timestamp
```

`Forms::storeSubmission()` is also available and returns just the UUID string for
backward compatibility. `RoundlyConsulting\Forms\Services\FormsService` (the object behind
the facade) exposes the same API and can be resolved from the container directly.

#### Closed forms are rejected

`submit()`/`storeSubmission()` reject submissions to a form that isn't accepting them — a
non-public form, or one whose `expires_at` has passed — by throwing
`RoundlyConsulting\Forms\Exceptions\FormSubmissionClosedException`. Pass `bypassClosed: true`
for trusted internal/admin submissions:

```php
Forms::submit($form, request(), $user, bypassClosed: true);
```

Two readable helpers back this: `$form->isExpired()` and `$form->isAcceptingSubmissions()`
(public **and** not expired). `$field->isRequired()` reports whether a field carries a
`required` rule.

### Draft submissions (save & resume)

Save a partial submission now and finalize it later. Drafts skip validation; finalizing runs
full validation before promoting the draft to a final submission:

```php
$draft = Forms::draft($form, request(), $user);   // returns SubmissionResult with a uuid
// ...later, resume by reusing the uuid (overwrites the prior draft values):
Forms::draft($form, request(), $user, uuid: $draft->uuid);
// finalize — validates, then marks the rows final and dispatches FormSubmitted:
Forms::finalize($draft->uuid);
```

Drafts are excluded from final-submission reads by default.

### Reading submissions

`Forms::submissions($form)` returns a fluent reader that assembles the per-field rows back
into one keyed `[field_key => value]` set per submission:

```php
$answers = Forms::submissions($form)
    ->forSender($user)   // optional
    ->latest()           // or ->oldest()
    ->get();             // Collection<AssembledSubmission>

$answers->first()->values;          // ['name' => 'Jane', 'email' => 'jane@example.com']
$answers->first()->value('name');   // 'Jane'
```

Drafts are excluded unless you call `->withDrafts()`.

### Editing a form's structure

`Forms::update($key)` returns a builder that diffs your definition against the persisted
structure and saves the changes in a transaction — adding new groups/fields, renaming or
reordering existing ones, and firing the `FormUpdated` / `Group*` / `Field*` events only for
records that actually changed. Records absent from the definition are left untouched:

```php
Forms::update('contact')
    ->name('Contact us')
    ->public()
    ->group('details', 'Your details', function (GroupBuilder $g): void {
        $g->field('name', 'Full name')->rules(['required']);
        $g->field('phone', 'Phone'); // added
    })
    ->save();
```

### File uploads

Map a field to the `file` type and the bundled `FileResolver` stores the upload on the
configured disk and persists the stored path as the submission value:

```php
$g->field('passport', 'Passport')->file();

// read back the path / public URL:
$resolver = Forms::find('kyc')->fields->firstWhere('key', 'passport')->resolver();
$resolver->fromStorage(); // 'form-uploads/abc.pdf'
$resolver->url();         // Storage::disk(...)->url($path)
```

### The HasForms trait

Add `RoundlyConsulting\Forms\Concerns\HasForms` to a user/sender model for a morph relation
and submit shortcuts:

```php
use RoundlyConsulting\Forms\Concerns\HasForms;

class User extends Authenticatable
{
    use HasForms;
}

$user->submitTo($form, request());     // delegates to Forms::submit with $user as sender
$user->draftTo($form, request());      // delegates to Forms::draft
$user->formSubmissions;                // morphMany of the user's submissions
```

### Declarative forms (`forms:sync`)

Define forms in `config('forms.definitions')` and sync them into the database — creating
missing forms and updating changed ones, idempotently:

```php
// config/forms.php
'definitions' => [
    [
        'key' => 'contact',
        'name' => 'Contact',
        'is_public' => true,
        'groups' => [
            ['key' => 'details', 'name' => 'Details', 'fields' => [
                ['key' => 'name', 'name' => 'Name', 'rules' => ['required']],
            ]],
        ],
    ],
],
```

```bash
php artisan forms:sync
```

`Forms::sync($definitions)` runs the same logic with an explicit definition list.

### Reacting to events

After a submission, the package dispatches
`RoundlyConsulting\Forms\Events\FormSubmitted`:

```php
use RoundlyConsulting\Forms\Events\FormSubmitted;

class NotifyAdminsOfSubmission
{
    public function handle(FormSubmitted $event): void
    {
        // $event->form        — the submitted Form
        // $event->uuid        — the submission reference id (string)
        // $event->fieldCount  — number of fields stored (int)
    }
}
```

The structure also emits lifecycle events you can listen to: `FormCreated`, `FormUpdated`,
`FormDeleted`, and the equivalent `Group*` and `Field*` events (each carrying the model).

### Exceptions

Lookups throw package-specific exceptions, all extending
`RoundlyConsulting\Forms\Exceptions\FormsException`:

- `FormNotFoundException` — no form matches the key.
- `MultipleFormsFoundException` — more than one form matches the key.
- `UnresolvableFieldException` — no resolver is registered for a field's type.
- `FormSubmissionClosedException` — submission attempted on a non-public or expired form.
- `DraftNotFoundException` — `finalize()` called with an unknown draft uuid.

Messages are translatable via the `forms::messages` namespace.

### Query scopes & soft deletes

`Form` ships query scopes: `public()`, `active()` (no expiry or not yet expired),
`expired()`, and `forKey($key)`. `Group` and `Field` expose `ordered()`:

```php
use RoundlyConsulting\Forms\Models\Form;

$openForms = Form::query()->public()->active()->get();
```

All four models use soft deletes — deleting a form keeps its rows and submissions in the
database (recoverable with `restore()` / queryable with `withTrashed()`). Deletes are not
cascaded, so soft-deleting a form leaves its groups, fields, and submissions intact.

### Custom field resolvers

A resolver controls how a field's value is read from the request (`toStorable`) and read back
from storage (`fromStorage`). The package ships `DefaultResolver` and `FileResolver` (see
**File uploads** above). Implement `RoundlyConsulting\Forms\Resolvers\Resolver` and map it
to a field `type` in `config/forms.php`:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Resolvers\Resolver;

class CheckboxResolver implements Resolver
{
    public function __construct(private Field $field) {}

    public function toStorable(Request $request, ?Model $sender = null): array
    {
        return ['value' => $request->boolean($this->field->path())];
    }

    public function fromStorage(?Model $sender = null): mixed
    {
        return $this->field->submissions()
            ->where('field_id', $this->field->getKey())
            ->value('value')['value'] ?? null;
    }
}
```

```php
// config/forms.php
'fields' => [
    'default' => \RoundlyConsulting\Forms\Resolvers\DefaultResolver::class,
    'checkbox' => \App\Forms\CheckboxResolver::class,
],
```

### Autofill

Set a field's `autofill` to a class implementing `RoundlyConsulting\Forms\Autofill\Autofill`
to compute a default value on the fly:

```php
use RoundlyConsulting\Forms\Autofill\Autofill;
use RoundlyConsulting\Forms\Models\Field;

class CurrentUserEmail implements Autofill
{
    public function fill(Field $field): mixed
    {
        return auth()->user()?->email;
    }
}

// $field->getAutofillValue() resolves the class and returns its fill() result,
// or the raw `autofill` string when it isn't a resolvable Autofill class.
```

### API resources

Ready-made `JsonResource`s render a form and its structure for API responses:

```php
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Resources\FormResource;

$form = Forms::find('my-form');

return FormResource::make($form);
```

`FormResource` nests `GroupResource` (when `groups` are loaded), which nests `FieldResource`
(when `fields` are loaded) — including each field's options, validations, and autofill state.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
