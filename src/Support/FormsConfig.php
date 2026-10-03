<?php

declare(strict_types=1);

namespace RoundlyConsulting\Forms\Support;

use RoundlyConsulting\Attributes\Enums\AttributeType;
use RoundlyConsulting\Forms\Resolvers\Resolver;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict readers for the package's non-boolean settings.
 *
 * A setting that is not set — absent, null, or blank like a host's `KEY=` — takes its default
 * (or, for an optional setting such as the upload disk, none; a blank map entry is unmapped).
 * Anything else unusable — a `publik` visibility, a non-string disk, `10MB` for a size, a field
 * type mapped to `integr`, a resolver that is not a {@see Resolver} — throws
 * {@see InvalidConfigurationException} naming the key, so a typo never quietly falls back.
 *
 * @internal
 */
final class FormsConfig
{
    public const string VISIBILITY_PRIVATE = 'private';

    public const string VISIBILITY_PUBLIC = 'public';

    private const string FIELD_TYPES = 'forms.field_types';

    private const string FIELDS = 'forms.fields';

    public static function bucket(): string
    {
        return self::string('forms.media.bucket', 'attachment');
    }

    /** `private` or `public`. */
    public static function visibility(): string
    {
        return Config::oneOf(
            'forms.media.visibility',
            [self::VISIBILITY_PRIVATE, self::VISIBILITY_PUBLIC],
            self::VISIBILITY_PRIVATE,
        );
    }

    /** The explicit upload disk, or null to choose one by visibility. */
    public static function disk(): ?string
    {
        return self::optionalString('forms.media.disk');
    }

    public static function privateDisk(): string
    {
        return self::string('forms.media.private_disk', 'local');
    }

    /**
     * The accepted mime types; empty (unset or `[]`) accepts any file.
     *
     * @return list<string>
     */
    public static function acceptedMimeTypes(): array
    {
        return self::stringList('forms.media.accepted_mime_types', self::unlessBlank(config('forms.media.accepted_mime_types')) ?? []);
    }

    /** The upload size cap in bytes, or null for media-library's own limit. */
    public static function maxFileSize(): ?int
    {
        return self::unlessBlank(config('forms.media.max_file_size')) === null
            ? null
            : Config::integer('forms.media.max_file_size', 1, 1);
    }

    /**
     * The responsive width ladder, or null for media-library's default ladder.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        $key = 'forms.media.responsive_widths';
        $widths = self::unlessBlank(config($key));

        if ($widths === null) {
            return null;
        }

        if (! is_array($widths) || ! array_is_list($widths)) {
            throw self::notA($key, 'list of positive integers', $widths);
        }

        $clean = [];

        foreach ($widths as $width) {
            // Validated under the setting's own key, so the message names it.
            $clean[] = Config::for([$key => $width])->integer($key, 1, 1);
        }

        return array_values(array_unique($clean));
    }

    /** The configured signed-URL lifetime in minutes, or null for media-library's default. */
    public static function configuredTemporaryUrlLifetime(): ?int
    {
        return self::unlessBlank(config('forms.media.temporary_url_lifetime')) === null
            ? null
            : Config::integer('forms.media.temporary_url_lifetime', 5, 1);
    }

    /** Lifetime, in minutes, of a signed attachment URL. */
    public static function temporaryUrlLifetime(): int
    {
        return self::configuredTemporaryUrlLifetime()
            ?? Config::integer('media.temporary_url_default_lifetime', 5, 1);
    }

    /**
     * The {@see AttributeType} a field type maps to through `forms.field_types`; a type the map
     * does not list, maps to a blank value (not set), or a field with no type is a plain string.
     * A mapped value that is not an AttributeType throws.
     */
    public static function fieldType(?string $type): AttributeType
    {
        $types = self::fieldTypeMap();
        $mapped = $type === null ? null : self::unlessBlank($types[$type] ?? null);

        if ($mapped === null) {
            return AttributeType::String_;
        }

        // Validated under the entry's own key, so the message names it.
        $key = self::FIELD_TYPES.'.'.$type;

        return Config::for([$key => $mapped])->enum($key, AttributeType::class);
    }

    /**
     * The resolver class for a field type — its own `forms.fields` entry, else the `default`
     * entry — or null when neither is mapped. A blank entry is not set (unmapped). A mapped
     * value that is not a {@see Resolver} class throws.
     *
     * @return class-string<Resolver>|null
     */
    public static function resolver(?string $type): ?string
    {
        $resolvers = self::resolverMap();

        if ($type !== null && self::unlessBlank($resolvers[$type] ?? null) !== null) {
            [$entry, $class] = [$type, $resolvers[$type]];
        } elseif (self::unlessBlank($resolvers['default'] ?? null) !== null) {
            [$entry, $class] = ['default', $resolvers['default']];
        } else {
            return null;
        }

        if (! is_string($class) || ! is_subclass_of($class, Resolver::class)) {
            throw InvalidConfigurationException::notAnImplementation(self::FIELDS.'.'.$entry, Resolver::class, $class);
        }

        return $class;
    }

    /**
     * The `forms.field_types` map (field type => AttributeType value), entries unchecked.
     *
     * @return array<array-key, mixed>
     */
    public static function fieldTypeMap(): array
    {
        return self::map(self::FIELD_TYPES);
    }

    /**
     * The `forms.fields` map (field type => Resolver class), entries unchecked.
     *
     * @return array<array-key, mixed>
     */
    public static function resolverMap(): array
    {
        return self::map(self::FIELDS);
    }

    /**
     * The declared form definitions (each handed to `FormDefinitionData::fromArray()`); the
     * array may be keyed (e.g. by form key) — only its values are used.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        $key = 'forms.definitions';
        $definitions = self::unlessBlank(config($key)) ?? [];

        if (! is_array($definitions)) {
            throw self::notA($key, 'list of form definition arrays', $definitions);
        }

        $clean = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                throw self::notA($key, 'list of form definition arrays', $definition);
            }

            /** @var array<string, mixed> $definition */
            $clean[] = $definition;
        }

        return $clean;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(string $key): array
    {
        $map = self::unlessBlank(config($key)) ?? [];

        if (! is_array($map)) {
            throw self::notA($key, 'map', $map);
        }

        return $map;
    }

    private static function string(string $key, string $default): string
    {
        return self::optionalString($key) ?? $default;
    }

    private static function optionalString(string $key): ?string
    {
        $value = self::unlessBlank(config($key));

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    /**
     * A raw config value, with a blank string (`''` or whitespace — a host's `KEY=`) read as
     * null: not set, exactly like an absent key.
     */
    private static function unlessBlank(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(string $key, mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw self::notA($key, 'list of non-empty strings', $values);
        }

        $strings = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw self::notA($key, 'list of non-empty strings', $value);
            }

            $strings[] = $value;
        }

        return $strings;
    }

    private static function notA(string $key, string $expectation, mixed $value): InvalidConfigurationException
    {
        $given = match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new InvalidConfigurationException("Configuration value [{$key}] must be a {$expectation}, [{$given}] given.");
    }
}
