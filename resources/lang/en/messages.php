<?php

declare(strict_types=1);

return [
    'form_not_found' => 'No form found for key [:key].',
    'multiple_forms_found' => 'Multiple forms found for key [:key].',
    'unresolvable_field' => 'No resolver registered for field type [:type].',
    'submission_closed' => 'Form [:key] is not accepting submissions.',
    'draft_not_found' => 'No draft submission found for uuid [:uuid].',
    'submission_not_found' => 'No submission found for uuid [:uuid].',
    'invalid_field_value' => 'The value for field [:field] is not a valid :type.',
    'reviews_disabled' => 'Submission reviews are disabled. Enable forms.approvals.enabled to route submissions through the approvals engine.',
    'submission_not_reviewable' => 'Submission [:uuid] cannot be reviewed while it is a draft.',
    'submission_review_without_reviewers' => 'Submission [:uuid] cannot be reviewed without naming its reviewers: pass them to requiring().',
];
