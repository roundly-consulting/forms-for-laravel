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
- Private uploads (the default `forms.media.visibility`) are now linked through short-lived
  signed URLs. `attachmentUrl()`, `attachmentUrls()` and `MediaFileResolver::url()` asked
  media-library for the upload's public URL, which it refuses for private media — so on the
  default config each of them threw `MediaCannotBeStreamed`. They now go through the new
  `resolveAttachmentUrl()` (public URL for public media, signed URL for private media). The
  config and README also warn that the default media disk (`public`) is web-served, so private
  uploads belong on a non-public disk.
