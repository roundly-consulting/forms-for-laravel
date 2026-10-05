<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/forms-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=forms-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/forms-for-laravel/main/art/hero.png" alt="Forms for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/forms-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/forms-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/forms-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/forms-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/forms-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/forms-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=forms-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Forms for Laravel

Define multi-step forms — groups and fields — in your database, validate user input against
per-field rules, and store submissions you can read back as typed answers. Everything is driven
by Eloquent models you can swap for your own.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/forms-for-laravel
php artisan vendor:publish --tag="forms-migrations"
php artisan vendor:publish --tag="approvals-migrations" --tag="attributes-migrations" --tag="media-migrations"
php artisan migrate
```

The migrations are not loaded automatically, and the companion packages' migrations are needed
too. If your senders have UUID/ULID keys, set `FORMS_KEY_TYPE` **before** migrating.

## Usage

Define a form once, with its groups (steps) and fields:

```php
use RoundlyConsulting\Forms\Facades\Forms;
use RoundlyConsulting\Forms\GroupBuilder;

Forms::define('contact', 'Contact us')
    ->public()
    ->group('details', 'Your details', function (GroupBuilder $g): void {
        $g->field('name', 'Name')->rules(['required', 'string']);
        $g->field('email', 'Email')->email()->required();
    })
    ->group('message', 'Message', function (GroupBuilder $g): void {
        $g->field('body', 'Message')->textarea()->required();
    })
    ->create();
```

Validate and store a submission — input arrives keyed by `form.group.field`
(`contact.details.name`):

```php
$form = Forms::find('contact');

Forms::validate(form: $form, request: $request);   // throws ValidationException on failure

$result = Forms::submit(form: $form, request: $request, sender: $request->user());
$result->uuid;                                      // the submission's reference id
```

Read the answers back, one keyed set per submission:

```php
$answers = Forms::submissions($form)->latest()->get();

$answers->first()->values;   // ['name' => 'Jane', 'email' => 'jane@example.com', 'body' => 'Hello!']
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/forms-for-laravel](https://roundly-consulting.com/open-source/docs/forms-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=forms-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=forms-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=forms-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
