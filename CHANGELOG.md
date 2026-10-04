# Changelog

All notable changes to `forms-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.2 - 2026-10-04

### Fixed

- A field that fails its type check now names the expected type in words, in the current locale
  ("must be a valid list" / "must be a valid date" instead of "a valid array" / "a valid datetime");
  the names are translatable under `forms::messages.types.*` (English and Slovak).
- `DraftNotFoundException` and `SubmissionNotFoundException` messages now write "UUID" in capitals.

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Multi-step forms stored in your database — forms, groups and fields as swappable Eloquent
  models — with per-field validation rules and stored submissions.
- A fluent builder, `Forms::define('contact', 'Contact us')->group(...)->create()`, with typed
  field shortcuts (`email()`, `number()`, `date()`, `select()`, `file()`, …) and custom messages.
- Conditional fields with `requiredWhen()` and `visibleWhen()`, enforced during validation.
- A `Forms` facade over an injectable `FormsManager` to `find()`, `validate()` and `submit()`,
  rejecting closed (non-public or expired) forms unless you pass `bypassClosed: true`.
- Draft submissions that save now and `finalize()` later, and a submissions reader that returns
  one keyed answer set per submission, filterable by sender, uuid (`whereUuid()`) and review
  outcome (`pendingApproval()`, `approved()`, `rejected()`).
- `Forms::submission($uuid)` to read one submission or draft (`get()`, `model()`), finalize it or
  open its review; an unknown uuid throws `SubmissionNotFoundException`.
- `Forms::update()` to change a form's structure in place, and declarative forms from config
  synced with `forms:sync`.
- Typed field values — numbers, booleans, dates and arrays read back as real PHP types — through
  attributes-for-laravel.
- File and image fields stored as media on the submission, served only through signed URLs when
  private, through media-library-for-laravel.
- Optional multi-approver review of whole submissions (`Forms::review($submission)` or by uuid)
  through approvals-for-laravel, mirrored back onto the submission status.
- A `HasForms` trait (`submitTo()`, `draftTo()`), custom field resolvers, autofill classes, query
  scopes and API resources (`FormResource`).
- Events for submissions and for every form, group and field change, and `Forms::fake()` — a
  recording `FormsManager` subtype that still performs — with assertions such as
  `assertSubmitted()`, `assertReviewOpened()` and an `assertNothing*` / `assertNo*` counterpart for
  each, covering calls through builders, `Forms::submission()`, `HasForms` and `forms:sync`.
