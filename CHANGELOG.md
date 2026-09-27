# Changelog

All notable changes to `forms-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Multi-step forms stored in your database — forms, groups and fields as swappable Eloquent
  models — with per-field validation rules and stored submissions.
- A fluent builder, `Forms::define('contact', 'Contact us')->group(...)->create()`, with typed
  field shortcuts (`email()`, `number()`, `date()`, `select()`, `file()`, …) and custom messages.
- Conditional fields with `requiredWhen()` and `visibleWhen()`, enforced during validation.
- A `Forms` facade to `find()`, `validate()` and `submit()`, rejecting closed (non-public or
  expired) forms unless you pass `bypassClosed: true`.
- Draft submissions that save now and `finalize()` later, and a submissions reader that returns
  one keyed answer set per submission.
- `Forms::update()` to change a form's structure in place, and declarative forms from config
  synced with `forms:sync`.
- Typed field values — numbers, booleans, dates and arrays read back as real PHP types — through
  attributes-for-laravel.
- File and image fields stored as media on the submission, served only through signed URLs when
  private, through media-library-for-laravel.
- Optional multi-approver review of whole submissions (`Forms::review()`) through
  approvals-for-laravel, mirrored back onto the submission status.
- A `HasForms` trait (`submitTo()`, `draftTo()`), custom field resolvers, autofill classes, query
  scopes and API resources (`FormResource`).
- Events for submissions and for every form, group and field change, and `Forms::fake()` with
  assertions such as `assertSubmitted()` for your tests.
