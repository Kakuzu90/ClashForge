<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Events\CocAccountAttached;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Services\AttachAccountService;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Auth\CapturesSecurityLog;
use Tests\Support\Coc\InteractsWithCoc;

// specs/09 §9: the in-game token lives only inside the request that verifies it.

uses(InteractsWithCoc::class, CapturesSecurityLog::class);

it('keeps the token out of every table, log, event and job it touches', function (bool $valid) {
    $this->captureSecurityLog();
    $this->captureAppLog();
    Queue::fake();
    $domainEvents = [CocAccountAttached::class, CocAccountVerified::class, CocAccountOwnershipTransferred::class];
    Event::fake($domainEvents);

    $token = 'tok-'.bin2hex(random_bytes(6));
    $tag = PlayerTag::from('#2PQ8GRJC');
    $user = User::factory()->create();
    $ulid = (string) app(AttachAccountService::class)->attach($user, $tag)->accountUlid;
    if ($valid) {
        $this->fakeCoc()->acceptToken($tag, $token);
    }

    $result = app(VerifyOwnershipService::class)->verify($user, $ulid, $token);

    $stored = CocAccount::query()->get()->toJson().CocAccountClaim::query()->get()->toJson().AuditLog::query()->get()->toJson();
    $logs = json_encode($this->securityEvents()).$this->appLogText();
    $jobs = Queue::pushedJobs() === [] ? '' : serialize(Queue::pushedJobs());

    expect($stored)->not->toContain($token)
        ->and($logs)->not->toContain($token)
        ->and(serialize(collect($domainEvents)->flatMap(fn (string $e) => Event::dispatched($e)->flatten())->all()))->not->toContain($token)
        ->and($jobs)->not->toContain($token)
        ->and($result->toJson())->not->toContain($token);
})->with(['valid token' => [true], 'invalid token' => [false]]);
