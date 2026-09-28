<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Forms\Commands\SyncFormsCommand;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Listeners\SyncSubmissionStatusFromApproval;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\GroupModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class FormsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('forms')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->hasCommands([
                SyncFormsCommand::class,
            ])
            ->hasFacadeAlias(Forms::class)
            // A form's fields and a submission's values are the host's own data — a
            // field key is a question it asks its users, a value is the answer, and
            // both are routinely personal. The section reports the *shape* of the
            // configuration only: models, switches, bounds and counts. No form key,
            // field type, resolver class, mime type or storage destination renders.
            ->contributesToAbout(static fn (): array => [
                'Form model' => class_basename(FormModel::class()),
                'Group model' => class_basename(GroupModel::class()),
                'Field model' => class_basename(FieldModel::class()),
                'Submission model' => class_basename(SubmissionModel::class()),
                'Aggregate model' => class_basename(FormSubmissionModel::class()),
                'Field resolvers' => self::listSize('forms.fields', 'resolver', 'DEFAULT ONLY'),
                'Typed field types' => self::listSize('forms.field_types', 'mapping', 'RAW STRINGS'),
                'Declared forms' => self::listSize('forms.definitions', 'definition', 'NONE'),
                'Attachments' => self::attachments(),
                'Attachment disk' => self::presence('forms.media.disk', 'MEDIA DEFAULT'),
                'Accepted types' => self::listSize('forms.media.accepted_mime_types', 'mime type', 'ANY'),
                'Max upload size' => self::bytes(),
                'Responsive widths' => self::listSize('forms.media.responsive_widths', 'width', 'MEDIA DEFAULT'),
                'Signed URL lifetime' => self::signedUrlLifetime(),
                'Submission review' => config('forms.approvals.enabled') === true ? 'ON (approvals)' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(FormsManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migrations' key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        Event::listen(ApprovalRequestResolved::class, SyncSubmissionStatusFromApproval::class);
    }

    /**
     * The size of a configured list, never its entries — a form key, a field type
     * and a mime type are all host vocabulary.
     */
    private static function listSize(string $key, string $noun, string $absent): string
    {
        $value = config($key);

        if (! is_array($value) || $value === []) {
            return $absent;
        }

        return sprintf('%d %s(s)', count($value), $noun);
    }

    /**
     * Whether a config key holds a non-empty value — never the value itself. The
     * attachment disk names a host filesystem.
     */
    private static function presence(string $key, string $absent): string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? 'SET' : $absent;
    }

    private static function attachments(): string
    {
        $bucket = config('forms.media.bucket');
        $visibility = config('forms.media.visibility');

        return sprintf(
            '%s bucket (%s)',
            is_string($bucket) && $bucket !== '' ? $bucket : 'attachment',
            is_string($visibility) && $visibility !== '' ? $visibility : 'private',
        );
    }

    private static function bytes(): string
    {
        $max = config('forms.media.max_file_size');

        return is_numeric($max) ? ((int) $max).' B' : 'MEDIA DEFAULT';
    }

    private static function signedUrlLifetime(): string
    {
        $minutes = config('forms.media.temporary_url_lifetime');

        return is_numeric($minutes) ? ((int) $minutes).' min' : 'MEDIA DEFAULT';
    }
}
