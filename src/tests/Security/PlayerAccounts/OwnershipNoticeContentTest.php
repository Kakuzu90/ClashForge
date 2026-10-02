<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendEmailNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Listeners\SendOwnershipNotice;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Notifications\CocAccountTakenOverNotification;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Support\Coc\InteractsWithCoc;

// specs/16 §4 (never emailed) and specs/11: a game name is third-party text, and a takeover notice
// must not reveal who took the account over.

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->holder = User::factory()->create();
    $this->claimant = User::factory()->create();
    $this->token = 'tok-'.bin2hex(random_bytes(6));
    $this->fakeCoc()->acceptToken($this->tag, 'holder-token');
    $this->fakeCoc()->acceptToken($this->tag, $this->token);
    $this->verify = fn (User $user, string $token) => app(VerifyOwnershipService::class)->verifyTag($user, $this->tag, $token);
});

/**
 * @return list<Email>
 */
function sentEmails(): array
{
    return app('mailer')->getSymfonyTransport()->messages()->map(fn (SentMessage $sent) => $sent->getOriginalMessage())->all();
}

it('keeps a hostile game name as text, and out of the takeover notice', function () {
    $account = CocAccount::factory()->for($this->claimant)->forTag('#2PQ8GRJC')->verified()->create(['ign' => '[x](https://e.vil)<b>hi</b>']);
    $claim = CocAccountClaim::factory()->create(['coc_account_id' => $account->id, 'user_id' => $this->claimant->id, 'tag_normalized' => '2PQ8GRJC']);
    $listener = app(SendOwnershipNotice::class);

    $listener->handleVerified(new CocAccountVerified($account->id, $this->claimant->id, $claim->id));
    $listener->handleTransferred(new CocAccountOwnershipTransferred($account->id, $this->holder->id, $this->claimant->id, VerificationMethod::ApiToken));

    [$verified, $takeover] = sentEmails();
    expect($verified->getHtmlBody())->toContain('[x](https://e.vil)&lt;b&gt;hi&lt;/b&gt;')
        ->and($verified->getHtmlBody())->not->toContain('href="https://e.vil"')
        ->and($verified->getHtmlBody())->not->toContain('<b>hi</b>')
        ->and($verified->getTextBody())->toContain('[x](https://e.vil)<b>hi</b>');

    // The new holder picks the name, so the always-sent takeover notice names the tag only.
    expect($takeover->getSubject())->toBe('Someone else verified one of your accounts')
        ->and($takeover->getHtmlBody().$takeover->getTextBody())->not->toContain('e.vil')
        ->and($takeover->getTextBody())->toContain('Someone verified #2PQ8GRJC with an in-game API token');

    // In-app the name is stored raw and rendered as text; Vue prints it without v-html.
    $row = Notification::query()->where('type', NotificationType::CocAccountVerified->value)->sole();
    expect($row->data['params']['name'])->toBe('[x](https://e.vil)<b>hi</b>')
        ->and(NotificationType::CocAccountVerified->render($row->data['params'])->body)->toContain('#2PQ8GRJC ([x](https://e.vil)<b>hi</b>)')
        ->and(Notification::query()->where('type', NotificationType::CocAccountTakenOver->value)->sole()->data['params'])->toBe(['tag' => '#2PQ8GRJC', 'method' => 'api_token']);
});

it('strips control and direction-override characters from a name', function () {
    $body = NotificationType::CocAccountVerified->render(['tag' => '#2PQ8GRJC', 'name' => "Pat\u{202E}moc.live\u{2066}\x07"])->body;

    expect($body)->toStartWith('#2PQ8GRJC (Patmoc.live) is now verified');
});

it('never puts the token or the new holder in the takeover notice', function () {
    ($this->verify)($this->holder, 'holder-token');
    ($this->verify)($this->claimant, $this->token);

    $takeover = collect(sentEmails())->first(fn (Email $email) => $email->getTo()[0]->getAddress() === $this->holder->email && str_contains($email->getSubject(), 'Someone else'));
    $rows = Notification::query()->get()->toJson();
    $holderRows = Notification::query()->where('notifiable_id', $this->holder->id)->get()->toJson();

    expect($takeover)->not->toBeNull()
        ->and($rows)->not->toContain($this->token)
        ->and($holderRows)->not->toContain($this->claimant->username)
        ->and($holderRows)->not->toContain($this->claimant->email);
    foreach ([$takeover->getHtmlBody(), $takeover->getTextBody(), $takeover->getSubject()] as $part) {
        expect($part)->not->toContain($this->token)
            ->and($part)->not->toContain($this->claimant->username)
            ->and($part)->not->toContain($this->claimant->email);
    }
    foreach (sentEmails() as $email) {
        expect($email->getHtmlBody().$email->getTextBody())->not->toContain($this->token);
    }
});

it('keeps the token out of the queued listener payloads', function () {
    Queue::fake();

    ($this->verify)($this->holder, 'holder-token');
    ($this->verify)($this->claimant, $this->token);

    $payloads = collect(Queue::pushedJobs())->flatten(1)->map(fn (array $pushed) => serialize($pushed['job']))->implode('');
    expect($payloads)->toContain('SendOwnershipNotice')
        ->and($payloads)->not->toContain($this->token);
});

it('keeps the verifier out of the jobs the listener queues', function () {
    $account = CocAccount::factory()->for($this->claimant)->forTag('#2PQ8GRJC')->verified()->create();
    $claim = CocAccountClaim::factory()->create(['coc_account_id' => $account->id, 'user_id' => $this->claimant->id, 'tag_normalized' => '2PQ8GRJC']);
    Queue::fake();

    app(SendOwnershipNotice::class)->handleVerified(new CocAccountVerified($account->id, $this->claimant->id, $claim->id));
    app(SendOwnershipNotice::class)->handleTransferred(new CocAccountOwnershipTransferred($account->id, $this->holder->id, $this->claimant->id, VerificationMethod::ApiToken));

    Queue::assertPushed(SendEmailNotificationJob::class);
    $notices = collect(Queue::pushedJobs()[SendQueuedNotifications::class] ?? [])->map(fn (array $pushed) => $pushed['job']->notification);
    expect($notices)->not->toBeEmpty()->each->toBeInstanceOf(CocAccountTakenOverNotification::class);
    $payloads = collect(Queue::pushedJobs())->flatten(1)->map(fn (array $pushed) => serialize($pushed['job']))->implode('');
    $takeover = $notices->map(fn ($notice) => serialize($notice))->implode('');
    expect($payloads)->not->toContain($this->claimant->email)
        ->and($takeover)->not->toContain($this->claimant->username)
        ->and($takeover)->not->toContain((string) $this->claimant->ulid);
});
