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
    ],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `models.form` | `class-string` | `Models\Form` | Model used for forms. |
| `models.group` | `class-string` | `Models\Group` | Model used for groups. |
| `models.field` | `class-string` | `Models\Field` | Model used for fields. |
| `models.submission` | `class-string` | `Models\Submission` | Model used for submissions. |
| `fields.default` | `class-string` | `Resolvers\DefaultResolver` | Resolver used for any field type without a specific mapping. |

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
from storage (`fromStorage`). Implement `RoundlyConsulting\Forms\Resolvers\Resolver` and map it
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
