<?php

declare(strict_types=1);

return [
    'form_not_found' => 'Pre kľúč [:key] sa nenašiel žiadny formulár.',
    'multiple_forms_found' => 'Pre kľúč [:key] sa našlo viacero formulárov.',
    'unresolvable_field' => 'Pre typ poľa [:type] nie je zaregistrovaný žiadny resolver.',
    'submission_closed' => 'Formulár [:key] neprijíma odpovede.',
    'draft_not_found' => 'Koncept odpovede s UUID [:uuid] sa nenašiel.',
    'submission_not_found' => 'Odpoveď s UUID [:uuid] sa nenašla.',
    'invalid_field_value' => 'Pole :attribute musí obsahovať :type.',
    // `:type` vety invalid_field_value podľa AttributeType — v tvare, ktorý do nej zapadá (akuzatív).
    'types' => [
        'string' => 'text',
        'integer' => 'celé číslo',
        'float' => 'číslo',
        'boolean' => 'hodnotu áno alebo nie',
        'array' => 'zoznam hodnôt',
        'datetime' => 'platný dátum',
    ],
    'reviews_disabled' => 'Schvaľovanie odpovedí je vypnuté. Zapnite forms.approvals.enabled, aby odpovede prechádzali schvaľovacím procesom.',
    'submission_not_reviewable' => 'Odpoveď [:uuid] nie je možné odoslať na schválenie, kým je konceptom.',
    'submission_review_without_reviewers' => 'Odpoveď [:uuid] nie je možné odoslať na schválenie bez určenia schvaľovateľov: odovzdajte ich metóde requiring().',
];
