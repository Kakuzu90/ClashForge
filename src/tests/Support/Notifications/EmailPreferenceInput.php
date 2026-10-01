<?php

namespace Tests\Support\Notifications;

class EmailPreferenceInput
{
    /** @return array{email_enabled: bool, email_categories: array<string, bool>} */
    public static function form(bool $enabled = true): array
    {
        return ['email_enabled' => $enabled, 'email_categories' => [
            'ownership' => true, 'bases' => true, 'moderation' => true,
            'recruitment' => true, 'marketplace' => true, 'social' => false, 'staff' => true,
        ]];
    }
}
