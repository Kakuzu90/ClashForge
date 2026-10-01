<?php

namespace App\Domain\Moderation\Queries;

use App\Domain\Moderation\Data\SanctionData;
use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\UserSanction;
use App\Models\User;

/**
 * An account's sanctions, newest first, for the admin user detail (specs/12 §4 "prior
 * sanctions"). The lift note comes from the `lift` / `unban` moderation action on the sanction.
 */
class SanctionHistoryQuery
{
    /**
     * @return list<SanctionData>
     */
    public function forUser(User $user, int $limit): array
    {
        $sanctions = UserSanction::query()
            ->with(['issuer:id,username', 'lifter:id,username'])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $liftNotes = ModerationAction::query()
            ->where('target_type', ModerationAction::TARGET_SANCTION)
            ->whereIn('target_id', $sanctions->pluck('id'))
            ->whereIn('action', [ModerationActionType::Lift->value, ModerationActionType::Unban->value])
            ->pluck('note', 'target_id');

        return array_values($sanctions->map(function (UserSanction $sanction) use ($liftNotes): SanctionData {
            [$state, $stateLabel] = match (true) {
                $sanction->lifted_at !== null => ['lifted', 'Lifted'],
                $sanction->isActive() => ['active', 'Active'],
                default => ['ended', 'Ended'],
            };

            return new SanctionData(
                typeLabel: $sanction->type->label(),
                reasonLabel: $sanction->reason_code->label(),
                publicReason: $sanction->public_reason,
                internalNote: $sanction->internal_note,
                issuedBy: $sanction->issuer?->username,
                startsAt: $sanction->starts_at->toIso8601String(),
                endsAt: $sanction->expires_at?->toIso8601String(),
                state: $state,
                stateLabel: $stateLabel,
                liftedBy: $sanction->lifter?->username,
                liftedAt: $sanction->lifted_at?->toIso8601String(),
                liftNote: $liftNotes[$sanction->id] ?? null,
            );
        })->all());
    }
}
