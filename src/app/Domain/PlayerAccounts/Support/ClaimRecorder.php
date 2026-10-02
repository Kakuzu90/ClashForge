<?php

namespace App\Domain\PlayerAccounts\Support;

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\PlayerAccounts\Enums\ClaimFailureReason;
use App\Domain\PlayerAccounts\Enums\ClaimMethod;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes `coc_account_claims` rows (one per attempt, specs/07) and the matching security event
 * (specs/11 §3: CoC claim attempts and verification failures). Neither ever holds the token.
 */
final class ClaimRecorder
{
    public function __construct(private readonly Request $request) {}

    public function record(User $user, PlayerTag $tag, ?int $accountId, ClaimStatus $status, ?ClaimFailureReason $reason = null, ClaimMethod $method = ClaimMethod::ApiToken, bool $fromRequest = true): CocAccountClaim
    {
        // `fromRequest: false` when someone else's request writes this user's row (a dispute decision).
        $http = $fromRequest && $this->request->route() !== null;
        $userAgent = $http ? $this->request->userAgent() : null;

        return CocAccountClaim::query()->forceCreate([
            'coc_account_id' => $accountId,
            'tag_normalized' => $tag->bare(),
            'user_id' => $user->id,
            'method' => $method,
            'status' => $status,
            'failure_reason' => $reason,
            'ip_hash' => $http ? IpHash::of($this->request->ip()) : null,
            'user_agent' => $userAgent === null || $userAgent === '' ? null : Str::limit($userAgent, 255, ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function securityEvent(string $event, User $user, PlayerTag $tag, array $context = [], bool $fromRequest = true): void
    {
        Log::channel('security')->warning($event, [
            'user' => $user->ulid,
            'tag' => $tag->value,
            ...$context,
            'ip_hash' => $fromRequest && $this->request->route() !== null ? IpHash::of($this->request->ip()) : null,
        ]);
    }
}
