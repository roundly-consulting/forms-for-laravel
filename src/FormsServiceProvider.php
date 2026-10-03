<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms;

use Closure;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Forms\Commands\SyncFormsCommand;
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\Listeners\SyncSubmissionStatusFromApproval;
use RoundlyConsulting\Forms\Support\FieldModel;
use RoundlyConsulting\Forms\Support\FormModel;
use RoundlyConsulting\Forms\Support\FormsConfig;
use RoundlyConsulting\Forms\Support\FormSubmissionModel;
use RoundlyConsulting\Forms\Support\GroupModel;
use RoundlyConsulting\Forms\Support\SubmissionModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
                'Field resolvers' => self::orInvalid(static fn (): string => self::count(FormsConfig::resolverMap(), 'resolver', 'DEFAULT ONLY')),
                'Typed field types' => self::orInvalid(static fn (): string => self::count(FormsConfig::fieldTypeMap(), 'mapping', 'RAW STRINGS')),
                'Declared forms' => self::orInvalid(static fn (): string => self::count(FormsConfig::definitions(), 'definition', 'NONE')),
                'Attachments' => self::orInvalid(static fn (): string => sprintf(
                    '%s bucket (%s)',
                    FormsConfig::bucket(),
                    FormsConfig::visibility(),
                )),
                'Attachment disk' => self::orInvalid(static fn (): string => FormsConfig::disk() === null ? 'MEDIA DEFAULT' : 'SET'),
                'Accepted types' => self::orInvalid(static fn (): string => self::count(FormsConfig::acceptedMimeTypes(), 'mime type', 'ANY')),
                'Max upload size' => self::orInvalid(static fn (): string => ($max = FormsConfig::maxFileSize()) === null ? 'MEDIA DEFAULT' : $max.' B'),
                'Responsive widths' => self::orInvalid(static fn (): string => self::count(FormsConfig::responsiveWidths() ?? [], 'width', 'MEDIA DEFAULT')),
                'Signed URL lifetime' => self::orInvalid(static fn (): string => ($minutes = FormsConfig::configuredTemporaryUrlLifetime()) === null ? 'MEDIA DEFAULT' : $minutes.' min'),
                'Submission review' => Config::boolean('forms.approvals.enabled') ? 'ON (approvals)' : 'OFF',
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
     * The size of a configured list, never its entries — a form key, a field type and a mime
     * type are all host vocabulary.
     *
     * @param  array<array-key, mixed>  $values
     */
    private static function count(array $values, string $noun, string $absent): string
    {
        return $values === [] ? $absent : sprintf('%d %s(s)', count($values), $noun);
    }

    /**
     * A strict read rendered for `about`, or `INVALID` when the setting is broken — so
     * `php artisan about` still works on a misconfigured host while every real read throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }
}
