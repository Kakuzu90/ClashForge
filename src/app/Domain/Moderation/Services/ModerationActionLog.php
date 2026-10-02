<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Models\ModerationAction;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Http\Request;

/**
 * Writes one immutable `moderation_actions` row for another module's staff decision (specs/07,
 * specs/12): an ownership dispute decided by an admin (specs/13 §5 step 6). Runs inside the
 * caller's transaction.
 */
class ModerationActionLog
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        User $actor,
        ModerationActionType $action,
        string $targetType,
        int $targetId,
        ?int $targetUserId,
        ReasonCode $reason,
        string $note,
        array $metadata = [],
    ): int {
        return ModerationAction::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_user_id' => $targetUserId,
            'reason_code' => $reason,
            'note' => $note,
            'metadata' => $metadata,
            'ip_hash' => $this->request->route() !== null ? IpHash::of($this->request->ip()) : null,
        ])->id;
    }
}
