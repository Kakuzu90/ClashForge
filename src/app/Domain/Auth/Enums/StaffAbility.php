<?php

namespace App\Domain\Auth\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * The staff rows of the specs/04 §2 permission matrix, each a Gate named by its value. Rows marked
 * own-resource (`○`) are ownership policies on their models, not staff abilities.
 */
enum StaffAbility: string implements HasLabelAndColor
{
    use EnumHelpers;

    case AccessAdmin = 'access-admin';
    case ViewUsers = 'view-users';
    case ViewPlatformStats = 'view-platform-stats';
    case ViewReportQueue = 'view-report-queue';
    case ClaimReportCase = 'claim-report-case';
    case HideContent = 'hide-content';
    case RemoveContent = 'remove-content';
    case WarnUser = 'warn-user';
    case RestrictUser = 'restrict-user';
    case SuspendUser = 'suspend-user';
    case BanUser = 'ban-user';
    case LiftSanction = 'lift-sanction';
    case ReviewMediaQuarantine = 'review-media-quarantine';
    case ResolveDisputes = 'resolve-disputes';
    case ForceOwnershipTransfer = 'force-ownership-transfer';
    case ApproveSellers = 'approve-sellers';
    case ResolveMarketplaceDisputes = 'resolve-marketplace-disputes';
    case ManageTags = 'manage-tags';
    case ViewModerationLog = 'view-moderation-log';
    case ViewAuditLog = 'view-audit-log';
    case ManageRoles = 'manage-roles';
    case ManageSettings = 'manage-settings';
    case HardDeleteUser = 'hard-delete-user';
    case Impersonate = 'impersonate';

    public function label(): string
    {
        return match ($this) {
            self::AccessAdmin => 'Open the admin area',
            self::ViewUsers => 'View user accounts',
            self::ViewPlatformStats => 'View platform stats',
            self::ViewReportQueue => 'View report queue',
            self::ClaimReportCase => 'Claim or assign a report case',
            self::HideContent => 'Hide content',
            self::RemoveContent => 'Remove content permanently',
            self::WarnUser => 'Warn user',
            self::RestrictUser => 'Restrict user',
            self::SuspendUser => 'Suspend user',
            self::BanUser => 'Ban user',
            self::LiftSanction => 'Unban or lift sanction',
            self::ReviewMediaQuarantine => 'Review media quarantine queue',
            self::ResolveDisputes => 'Resolve ownership dispute',
            self::ForceOwnershipTransfer => 'Force ownership transfer',
            self::ApproveSellers => 'Approve marketplace seller',
            self::ResolveMarketplaceDisputes => 'Resolve marketplace dispute',
            self::ManageTags => 'Manage tags and categories',
            self::ViewModerationLog => 'View moderation log',
            self::ViewAuditLog => 'View audit log',
            self::ManageRoles => 'Change user roles',
            self::ManageSettings => 'Manage feature flags and settings',
            self::HardDeleteUser => 'Hard-delete a user',
            self::Impersonate => 'Impersonate a user',
        };
    }

    /**
     * The colour of the lowest role that holds it; danger for the ability nobody has.
     */
    public function color(): string
    {
        return $this->minimumRole()?->color() ?? 'state-danger';
    }

    /**
     * The lowest role holding this ability; null means nobody (FR-ADMIN-6: no impersonation).
     */
    public function minimumRole(): ?Role
    {
        return match ($this) {
            self::ViewReportQueue,
            self::ClaimReportCase,
            self::HideContent,
            self::WarnUser,
            self::RestrictUser,
            self::ReviewMediaQuarantine,
            // Moderators see their own actions only; the query scopes that (specs/04 §2).
            self::ViewModerationLog => Role::Moderator,
            // Moderators work from the reports page outside /admin (owner decision, 2026-10-02).
            self::AccessAdmin,
            // Admin user list and detail show email, which only admins see (specs/11 §5).
            self::ViewUsers,
            // Dashboard sign-ups, failed jobs and media storage (FR-ADMIN-5).
            self::ViewPlatformStats,
            self::RemoveContent,
            self::SuspendUser,
            self::BanUser,
            self::LiftSanction,
            self::ResolveDisputes,
            self::ForceOwnershipTransfer,
            self::ApproveSellers,
            self::ResolveMarketplaceDisputes,
            self::ManageTags,
            self::ViewAuditLog => Role::Admin,
            self::ManageRoles,
            self::ManageSettings,
            self::HardDeleteUser => Role::SuperAdmin,
            self::Impersonate => null,
        };
    }

    /**
     * Opens or lists something without changing it.
     */
    public function isReadOnly(): bool
    {
        return in_array($this, [self::AccessAdmin, self::ViewUsers, self::ViewPlatformStats, self::ViewReportQueue, self::ViewModerationLog, self::ViewAuditLog], true);
    }

    public function grantedTo(Role $role): bool
    {
        $minimum = $this->minimumRole();

        return $minimum !== null && $role->includes($minimum);
    }
}
