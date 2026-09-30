<?php

// Login copy (specs/11 "Account enumeration"): one message for an unknown email and a wrong password.
return [
    'failed' => 'That email and password do not match. Check both and try again.',
    'password' => 'That password is not right.',
    'throttle' => 'Too many attempts. Try again in :seconds seconds.',
    // Shown only after the right password (specs/04 §1).
    'banned' => 'This account is banned, so it cannot sign in.',
    'banned_reason' => 'This account is banned, so it cannot sign in. Reason: :reason',
];
