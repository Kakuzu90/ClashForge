<?php

// House wording for the framework messages our forms show most. Every other key falls back to
// Laravel's own lang file (the app file is merged over it, key by key).
return [
    'confirmed' => 'The two :attribute entries do not match.',
    'email' => 'Enter a valid email address.',
    'password' => [
        'uncompromised' => 'This password appears in a known data breach. Choose a different one.',
    ],
];
