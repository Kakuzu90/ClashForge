<?php

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendEmailNotificationJob;
use App\Domain\Notifications\Notifications\NonSecurityEmail;
use App\Domain\Notifications\Services\EmailDeliveryService;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('renders only catalogue text instead of arbitrary event parameters or secrets', function () {
    Mail::fake();
    $user = User::factory()->create();
    $secrets = ['SENSITIVE-TOKEN', 'private-report-evidence', 'another-user@example.test', '<img src=x onerror=alert(1)>'];
    (new SendEmailNotificationJob($user->id, NotificationType::MediaProcessingFailed, 'media-id', [
        'token' => $secrets[0], 'evidence' => $secrets[1], 'email' => $secrets[2], 'collection' => $secrets[3],
    ]))->handle(app(EmailDeliveryService::class));

    Mail::assertSent(NonSecurityEmail::class, function (NonSecurityEmail $mail) use ($secrets): bool {
        foreach ($secrets as $secret) {
            $mail->assertDontSeeInHtml($secret);
            $mail->assertDontSeeInText($secret);
        }
        $mail->assertSeeInText('Your upload failed to process');

        return true;
    });
});
