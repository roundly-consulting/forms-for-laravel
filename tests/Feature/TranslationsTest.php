<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

dataset('translation files', ['messages']);

dataset('translated locales', ['sk']);

it('ships the same keys in every language', function (string $file, string $locale): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $translated = Arr::dot(require __DIR__.'/../../resources/lang/'.$locale.'/'.$file.'.php');

    expect($en)->not->toBeEmpty()
        ->and(array_keys($translated))->toBe(array_keys($en));
})->with('translation files')->with('translated locales');

it('keeps every placeholder in every language', function (string $file, string $locale): void {
    $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
    $translated = Arr::dot(require __DIR__.'/../../resources/lang/'.$locale.'/'.$file.'.php');

    $placeholders = static function (mixed $line): array {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', is_string($line) ? $line : '', $matches);
        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    };

    foreach ($en as $key => $line) {
        expect($placeholders($translated[$key] ?? null))->toBe($placeholders($line), "{$locale}: {$file}.{$key}");
    }
})->with('translation files')->with('translated locales');

it('serves slovak through the package namespace', function (): void {
    app()->setLocale('sk');

    expect(trans('forms::messages.form_not_found', ['key' => 'contact']))->toBe('Pre kľúč [contact] sa nenašiel žiadny formulár.')
        ->and(trans('forms::messages.invalid_field_value', ['attribute' => 'vek', 'type' => 'integer']))
        ->toBe('Pole vek musí obsahovať platnú hodnotu typu integer.');

    app()->setLocale('en');

    expect(trans('forms::messages.form_not_found', ['key' => 'contact']))->toBe('No form found for key [contact].')
        ->and(trans('forms::messages.invalid_field_value', ['attribute' => 'age', 'type' => 'integer']))
        ->toBe('The age field must be a valid integer.');
});
