# Changelog

All notable changes to `forms-for-laravel` will be documented in this file.

## Unreleased

### Fixed

- With `forms.key_type` set to `uuid` or `ulid`, submissions now store the sender's real key.
  The write paths (`CreateFormSubmissionAction`, `SubmissionData::forField()`) cast it to an
  integer, so a uuid/ulid sender was stored as `0` or a digit prefix (`'0199…'` → `199`): its
  submissions never showed up under `$sender->formSubmissions`, `forSender()` or a resolver's
  `fromStorage($sender)`, and a Postgres uuid column rejected the write outright.
  `SubmissionData::$senderId` and `AssembledSubmission::$senderId` are now `int|string|null`.
