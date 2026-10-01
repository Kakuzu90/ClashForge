<?php

// Moderation limits (specs/12 §6). Reason codes are App\Domain\Moderation\Enums\ReasonCode.
return [

    'sanctions' => [
        // Suspensions run 1 to 90 days (specs/12 §6).
        'suspension_max_days' => 90,
        // `user_sanctions.public_reason`: shown to the account holder.
        'public_reason_max' => 255,
        // The internal note and the lift note, for staff only.
        'note_max' => 2000,
    ],

];
