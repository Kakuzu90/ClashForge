<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Notifications\NonSecurityEmail;
use App\Domain\PlayerAccounts\Enums\DisputeDecision;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

// P2-18 (owner decision 2026-10-06): a dispute notice names the tag only. Never the other party's
// username or email, their statements, or the admin's internal note (specs/16 §4 "Never emailed").

it('keeps the other party, their words and the internal note out of every dispute notice', function (DisputeDecision $decision) {
    Mail::fake();
    $holder = User::factory()->create(['username' => 'holder_secret', 'email' => 'holder-secret@example.com']);
    $claimant = User::factory()->create(['username' => 'claimant_secret', 'email' => 'claimant-secret@example.com']);
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    $disputes = app(DisputeService::class);
    $ulid = (string) $disputes->open($claimant, PlayerTag::from('#2PQ8GRJC'), 'claimant-statement-text')->disputeUlid;
    $disputes->respond($holder, $ulid, 'holder-statement-text');
    $disputes->decide(User::factory()->admin()->create(), $ulid, $decision, 'internal-note-text');

    $secrets = ['holder_secret', 'claimant_secret', 'holder-secret@example.com', 'claimant-secret@example.com', 'claimant-statement-text', 'holder-statement-text', 'internal-note-text'];
    $notices = Notification::query()->where('type', 'like', 'coc_dispute_%')->get();
    expect($notices)->not->toBeEmpty();

    foreach ($notices as $notice) {
        $rendered = NotificationType::from($notice->type)->render($notice->data['params']);
        $text = json_encode($notice->data).$rendered->title.$rendered->body.$rendered->url;
        foreach ($secrets as $secret) {
            expect($text)->not->toContain($secret);
        }
    }
    $emails = Mail::sent(NonSecurityEmail::class);
    expect($emails)->not->toBeEmpty();
    foreach ($emails as $mail) {
        $html = $mail->render();
        foreach ($secrets as $secret) {
            expect($html)->not->toContain($secret);
        }
    }
})->with([DisputeDecision::Transfer, DisputeDecision::Deny, DisputeDecision::Suspend, DisputeDecision::AskClaimant, DisputeDecision::AskHolder]);
