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
- Resuming a draft (`Forms::draft(..., uuid: $uuid)`) now only accepts a draft of the same form.
  Given a finalized submission's uuid it flipped the aggregate back to `Draft` and wrote new draft
  rows next to the final ones (finalizing again left two final rows per field); given another
  form's draft it deleted that draft's rows and moved its aggregate to the new form. Both — and
  a malformed uuid, which Postgres rejected mid-query — now throw `DraftNotFoundException` and
  leave the stored submission untouched.
- A closed form (non-public or past `expires_at`) no longer accepts a final submission by any
  path. Only `submit()` checked it: `draft()` + `finalize()` — and the raw `createSubmission()`,
  whose rows read as final — went through on a closed form, and a draft saved while the form was
  open could be finalized after it closed. `draft()`, `draftTo()`, `finalize()` and
  `createSubmission()` now throw `FormSubmissionClosedException` (checked again at finalize time)
  and accept `bypassClosed: true` like `submit()`. New `Form::ensureAcceptingSubmissions()`.
