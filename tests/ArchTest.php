<?php

declare(strict_types=1);

use RoundlyConsulting\Forms\Exceptions\FormsException;
use RoundlyConsulting\Forms\Models\Field;
use RoundlyConsulting\Forms\Models\Form;
use RoundlyConsulting\Forms\Models\FormSubmission;
use RoundlyConsulting\Forms\Models\Group;
use RoundlyConsulting\Forms\Models\Submission;
use RoundlyConsulting\Forms\Resolvers\DefaultResolver;
use RoundlyConsulting\Forms\Resolvers\MediaFileResolver;
use RoundlyConsulting\Forms\Resources\FieldResource;
use RoundlyConsulting\Forms\Resources\FormResource;
use RoundlyConsulting\Forms\Resources\GroupResource;
use RoundlyConsulting\Forms\Services\FormsService;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The arch presets. Forms shipped **no arch file at all** before this row — the same gap
 * that let jwt ship `final` on a config-swappable model (the fleet's 7×-shipped fatal).
 */
ArchPresets::strictTypes('RoundlyConsulting\Forms');

/**
 * Exempt from finality, each deliberately:
 *  - the five models — `forms.models.*` invites a host to subclass each one; `final` is a
 *    PHP fatal the moment a host uses the documented seam. Pinned positively below.
 *  - the three resources — published as stubs into a host's app (`forms-resources`) and
 *    documented as the thing to extend/customise.
 *  - the two resolvers — `forms.fields.*` names a resolver class per field type, and a host
 *    writes its own by extending these.
 *  - FormsException — the base every forms error extends, so a host can catch uniformly.
 *  - FormsService — the package's own FormsFake extends it, which is how `Forms::fake()`
 *    works. `final` here would break a feature this package ships.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Forms', [
    Form::class,
    Group::class,
    Field::class,
    Submission::class,
    FormSubmission::class,
    FormResource::class,
    GroupResource::class,
    FieldResource::class,
    DefaultResolver::class,
    MediaFileResolver::class,
    FormsException::class,
    FormsService::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal. Also pins that each `forms.models.*`
 * key really defaults to the packaged model, so the seam cannot rot in the other direction.
 *
 * `forms.fields.default|file|image` are deliberately NOT here: they resolve a Resolver, not
 * an Eloquent model, so they are outside what this preset means by a swappable model. The
 * row spec counted them and scored `Swap? 8`; the real count is 5.
 */
ArchPresets::swappableModelsAreNotFinal([
    Form::class => 'forms.models.form',
    Group::class => 'forms.models.group',
    Field::class => 'forms.models.field',
    Submission::class => 'forms.models.submission',
    FormSubmission::class => 'forms.models.form_submission',
]);

/**
 * Forms mints submission uuids (Str::uuid) and signs attachment URLs through Laravel's
 * signer. This pins that a hand-rolled scheme never lands here instead.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Forms');

/**
 * All five `forms.models.*` keys resolve through the Support seam. Adopted on the
 * pre-classified rule (Swap? > 0): forms has exactly the shape the preset targets — real
 * Eloquent models behind `*_model`-shaped keys, every call site going through the seam.
 *
 * The keys are declared explicitly: they are nested under `models.` rather than named
 * `*_model`, which the preset's shape inference does not see.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', [
    'forms.models.form',
    'forms.models.group',
    'forms.models.field',
    'forms.models.submission',
    'forms.models.form_submission',
]);

/**
 * The morph-key seam, guarded. Forms migrated its submission morph columns off raw
 * `$table->morphs()` onto `morphKey($name, KeyType::…)` so a uuid/ulid host can flip its
 * whole graph coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite
 * type affinity hides it. This pin reds if a future migration reintroduces a raw morph and
 * bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: forms' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`. If this goes
 * red the graph is wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
