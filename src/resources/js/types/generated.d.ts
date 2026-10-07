declare namespace App {
namespace Domain {
namespace Audit {
namespace Enums {
export type AuditAction = 'role.changed' | 'sanction.applied' | 'sanction.lifted' | 'sanction.expired' | 'user.anonymised' | 'user.username_changed' | 'coc_account.verified' | 'coc_account.released' | 'coc_dispute.opened' | 'coc_dispute.responded' | 'coc_dispute.info_requested' | 'coc_dispute.escalated' | 'coc_dispute.closed' | 'coc_dispute.evidence_viewed' | 'coc_dispute.evidence_removed' | 'failed_job.retried' | 'failed_job.deleted';
export type AuditSubject = 'user' | 'coc_account' | 'coc_account_dispute' | 'failed_job';
}
}
namespace Auth {
namespace Data {
export type AdminUserDetailData = {
ulid: string,
username: string,
email: string,
emailVerifiedAt: string | null,
roleLabel: string,
statusLabel: string,
statusTone: string,
statusReason: string | null,
statusEndsAt: string | null,
joinedAt: string,
lastSignInAt: string | null,
activeSessions: number,
deletedAt: string | null,
};
export type AdminUserRowData = {
ulid: string,
username: string,
avatarUrl: string | null,
email: string,
emailVerified: boolean,
roleLabel: string,
statusLabel: string,
statusTone: string,
joinedAt: string,
lastSignInAt: string | null,
deleted: boolean,
};
export type SessionData = {
key: string,
deviceLabel: string,
country: string | null,
lastActiveAt: string,
signedInAt: string | null,
isCurrent: boolean,
};
export type SignupCountData = {
total: number,
verified: number,
};
export type SignupStatsData = {
last24Hours: App.Domain.Auth.Data.SignupCountData,
last7Days: App.Domain.Auth.Data.SignupCountData,
last30Days: App.Domain.Auth.Data.SignupCountData,
};
export type UsernameSettingsData = {
username: string,
canChange: boolean,
needsVerifiedEmail: boolean,
nextChangeAt: string | null,
changeDays: number,
reservationDays: number,
minLength: number,
maxLength: number,
};
}
namespace Enums {
export type EmailChangeOutcome = 'pending' | 'changed' | 'already_changed' | 'taken' | 'wrong_account' | 'invalid';
export type EmailVerificationOutcome = 'pending' | 'verified' | 'already_verified' | 'invalid';
export type Role = 'user' | 'moderator' | 'admin' | 'super_admin';
export type StaffAbility = 'access-admin' | 'view-users' | 'view-platform-stats' | 'manage-failed-jobs' | 'view-report-queue' | 'claim-report-case' | 'hide-content' | 'remove-content' | 'warn-user' | 'restrict-user' | 'suspend-user' | 'ban-user' | 'lift-sanction' | 'review-media-quarantine' | 'resolve-disputes' | 'force-ownership-transfer' | 'approve-sellers' | 'resolve-marketplace-disputes' | 'manage-tags' | 'view-moderation-log' | 'view-audit-log' | 'manage-roles' | 'manage-settings' | 'hard-delete-user' | 'impersonate';
export type UserStatus = 'active' | 'restricted' | 'suspended' | 'banned' | 'pending_deletion';
}
}
namespace Bases {
namespace Enums {
export type BaseCategory = 'war' | 'cwl' | 'farming' | 'trophy' | 'legend' | 'anti_3_star' | 'anti_2_star' | 'hybrid' | 'progress' | 'troll';
export type BaseModerationState = 'clean' | 'flagged' | 'under_review' | 'actioned';
export type BaseStatus = 'draft' | 'processing' | 'published' | 'hidden' | 'removed';
export type BaseVisibility = 'public' | 'unlisted' | 'private';
}
}
namespace Clans {
namespace Data {
export type ClanSummaryData = {
tag: string,
name: string,
level: number | null,
badgeUrls: Record<string, string>,
};
}
namespace Enums {
export type ClanRole = 'member' | 'admin' | 'coLeader' | 'leader';
}
}
namespace CocIntegration {
namespace Data {
export type CocApiHealthData = {
state: App.Domain.CocIntegration.Enums.CocCircuitState,
reason: App.Domain.CocIntegration.Enums.CocCircuitReason | null,
openUntil: string | null,
keysHealthy: number,
keysTotal: number,
windowHours: number,
calls: number,
cacheHits: number,
failures: number,
failureRate: number | null,
topError: string | null,
syncWindowMinutes: number,
syncAttempts: number,
syncSuccesses: number,
syncSuccessRate: number | null,
syncAlert: number,
syncBelowAlert: boolean,
syncStopped: number,
};
export type CocKeyData = {
id: string,
healthy: boolean,
reason: string | null,
unhealthySince: string | null,
};
}
namespace Enums {
export type CocCircuitReason = 'failures' | 'maintenance';
export type CocCircuitState = 'closed' | 'open' | 'half_open';
export type CocFailureReason = 'throttled' | 'maintenance' | 'server_error' | 'timeout' | 'no_healthy_key' | 'malformed' | 'circuit_open' | 'deadline';
export type CocLookupStatus = 'found' | 'not_found' | 'unavailable';
export type CocPriority = 'interactive' | 'background';
export type SyncResourceType = 'coc_account' | 'clan';
export type SyncTier = 'hot' | 'warm' | 'cold' | 'frozen';
export type TokenVerificationStatus = 'ok' | 'invalid' | 'not_found' | 'unavailable';
}
}
namespace GameAssets {
namespace Data {
export type GameAssetData = {
kind: App.Domain.GameAssets.Enums.GameAssetKind,
url: string | null,
alt: string,
short: string,
width: number | null,
height: number | null,
};
}
namespace Enums {
export type GameAssetCategory = 'troop' | 'hero' | 'spell' | 'equipment' | 'pet' | 'siege_machine' | 'town_hall' | 'league';
export type GameAssetKind = 'unit' | 'town_hall' | 'league' | 'clan_badge';
export type Village = 'home' | 'builderBase';
}
}
namespace Media {
namespace Data {
export type MediaCollectionUsageData = {
collection: string,
label: string,
bytes: number,
objects: number,
};
export type MediaStorageData = {
totalBytes: number,
totalObjects: number,
collections: App.Domain.Media.Data.MediaCollectionUsageData[],
awaitingPurgeBytes: number,
awaitingPurgeObjects: number,
purgeAfterDays: number,
quarantinedCount: number,
};
export type MediaVariantData = {
name: App.Domain.Media.Enums.VariantName,
url: string,
width: number,
height: number,
};
export type UploadCollectionData = {
value: App.Domain.Media.Enums.MediaCollection,
label: string,
maxBytes: number,
accept: string,
typesLabel: string,
};
export type UploadStatusData = {
mediaUlid: string,
status: App.Domain.Media.Enums.MediaStatus,
finished: boolean,
failureMessage: string | null,
width: number | null,
height: number | null,
variants: App.Domain.Media.Data.MediaVariantData[],
};
export type UploadTicketData = {
mediaUlid: string,
uploadUrl: string,
uploadMethod: string,
uploadHeaders: Record<string, string>,
expiresIn: number,
maxSize: number,
};
}
namespace Enums {
export type MediaCollection = 'avatar' | 'account_image' | 'base_screenshot' | 'base_video' | 'evidence' | 'portfolio';
export type MediaFailureReason = 'object_missing' | 'size_mismatch' | 'undecodable' | 'dimensions_too_large' | 'dimensions_too_small' | 'animated' | 'suspicious_content' | 'processing_error';
export type MediaKind = 'image' | 'video';
export type MediaStatus = 'pending' | 'uploaded' | 'processing' | 'ready' | 'failed' | 'quarantined' | 'deleting';
export type MediaVisibility = 'public' | 'private';
export type VariantName = 'thumb' | 'card' | 'full' | 'poster' | 'video_720p';
}
}
namespace Moderation {
namespace Data {
export type SanctionAbilitiesData = {
suspend: boolean,
ban: boolean,
lift: boolean,
activeType: string | null,
};
export type SanctionData = {
typeLabel: string,
reasonLabel: string,
publicReason: string,
internalNote: string,
issuedBy: string | null,
startsAt: string,
endsAt: string | null,
state: string,
stateLabel: string,
liftedBy: string | null,
liftedAt: string | null,
liftNote: string | null,
};
}
namespace Enums {
export type ModerationActionType = 'hide' | 'unhide' | 'remove' | 'restore' | 'warn' | 'restrict' | 'suspend' | 'ban' | 'lift' | 'unban' | 'dismiss' | 'escalate' | 'transfer_ownership' | 'approve_seller' | 'reject_listing' | 'release_tag';
export type ReasonCode = 'account_trading' | 'scam' | 'false_ownership' | 'nsfw' | 'hate' | 'harassment' | 'impersonation' | 'stolen_content' | 'off_platform_payment' | 'spam' | 'wrong_category' | 'other';
export type SanctionType = 'warning' | 'restriction' | 'suspension' | 'ban';
}
}
namespace Notifications {
namespace Data {
export type EmailCategoryData = {
key: string,
label: string,
enabled: boolean,
locked: boolean,
hint: string | null,
};
export type EmailPreferencesData = {
emailEnabled: boolean,
categories: App.Domain.Notifications.Data.EmailCategoryData[],
canUpdate: boolean,
};
export type InAppMessageData = {
type: App.Domain.Notifications.Enums.NotificationType,
params: Record<string, string | number | boolean | null>,
groupKey: string | null,
};
export type NotificationItemData = {
id: string,
category: string | null,
title: string,
body: string,
hasTarget: boolean,
read: boolean,
createdAt: string,
};
export type NotificationSliceData = {
entries: App.Domain.Notifications.Data.NotificationItemData[],
newerCursor: string | null,
olderCursor: string | null,
};
export type RenderedNotificationData = {
title: string,
body: string,
url: string | null,
actionLabel: string | null,
};
}
namespace Enums {
export type NotificationCategory = 'security' | 'ownership' | 'bases' | 'moderation' | 'recruitment' | 'marketplace' | 'social' | 'staff';
export type NotificationType = 'email_verified' | 'password_changed' | 'new_device_sign_in' | 'account_suspended' | 'account_banned' | 'sanction_ended' | 'media_processing_failed' | 'coc_account_verified' | 'coc_account_taken_over' | 'coc_account_not_found' | 'coc_account_released' | 'coc_dispute_opened' | 'coc_dispute_reminder' | 'coc_dispute_info_requested' | 'coc_dispute_closed' | 'coc_dispute_evidence_removed';
export type UnsubscribeOutcome = 'pending' | 'unsubscribed' | 'invalid';
}
}
namespace Operations {
namespace Data {
export type FailedJobActionResultData = {
done: number,
skipped: number,
kept: number,
left: number,
};
export type FailedJobClassData = {
name: string | null,
count: number,
lastFailedAt: string,
};
export type FailedJobGroupData = {
name: string | null,
queues: string[],
count: number,
lastHour: number,
firstFailedAt: string,
lastFailedAt: string,
};
export type FailedJobListData = {
name: string | null,
total: number,
jobs: App.Domain.Operations.Data.FailedJobRowData[],
};
export type FailedJobRowData = {
uuid: string,
queue: string,
failedAt: string,
};
export type FailedJobsByClassData = {
total: number,
lastHour: number,
alertPerHour: number,
overThreshold: boolean,
retentionDays: number,
classes: App.Domain.Operations.Data.FailedJobGroupData[],
};
export type FailedJobsSummaryData = {
lastHour: number,
last24Hours: number,
alertPerHour: number,
overThreshold: boolean,
topClasses: App.Domain.Operations.Data.FailedJobClassData[],
};
export type QueueStatData = {
name: string,
waiting: number,
delayed: number,
reserved: number,
oldestWaitSeconds: number | null,
maxWaitSeconds: number | null,
maxDepth: number,
overWait: boolean,
overDepth: boolean,
};
export type SchedulerStatusData = {
state: App.Domain.Operations.Enums.SchedulerState,
lastBeatAt: string | null,
ageSeconds: number | null,
maxAgeSeconds: number,
};
}
namespace Enums {
export type SchedulerState = 'running' | 'stopped' | 'unknown';
}
}
namespace PlayerAccounts {
namespace Data {
export type AccountClanData = {
tag: string,
name: string,
level: number | null,
roleLabel: string | null,
badge: App.Domain.GameAssets.Data.GameAssetData,
};
export type AccountDetailData = {
card: App.Domain.PlayerAccounts.Data.PlayerCardData,
stats: App.Domain.PlayerAccounts.Data.AccountStatData[],
builderLeagueName: string | null,
builderLeague: App.Domain.GameAssets.Data.GameAssetData | null,
builderHall: App.Domain.GameAssets.Data.GameAssetData | null,
deltaDays: number,
notFound: boolean,
isOwn: boolean,
canVerify: boolean,
canDetach: boolean,
canFeature: boolean,
canRefresh: boolean,
refreshWaitSeconds: number,
indexable: boolean,
images: App.Domain.PlayerAccounts.Data.AccountImageData[],
canManageImages: boolean,
imagesMax: number,
disputeUlid: string | null,
};
export type AccountImageData = {
ulid: string,
card: App.Domain.Media.Data.MediaVariantData | null,
full: App.Domain.Media.Data.MediaVariantData | null,
processing: boolean,
failed: boolean,
};
export type AccountStatData = {
key: string,
label: string,
value: number | null,
delta: number | null,
};
export type AttachResultData = {
outcome: App.Domain.PlayerAccounts.Enums.AttachOutcome,
tag: string,
player: App.Domain.PlayerAccounts.Data.CocPlayerPreviewData | null,
accountUlid: string | null,
holderUsername: string | null,
retryAfter: number | null,
};
export type CocPlayerPreviewData = {
tag: string,
name: string,
townHallLevel: number | null,
trophies: number | null,
expLevel: number | null,
clanName: string | null,
leagueName: string | null,
stale: boolean,
fetchedAt: string | null,
};
export type DisputeClaimData = {
username: string,
methodLabel: string,
statusLabel: string,
failureLabel: string | null,
at: string,
};
export type DisputeDecisionOptionData = {
value: App.Domain.PlayerAccounts.Enums.DisputeDecision,
label: string,
available: boolean,
unavailableReason: string | null,
};
export type DisputeEvidenceData = {
party: string,
note: string | null,
images: App.Domain.PlayerAccounts.Data.DisputeEvidenceImageData[],
at: string,
opening: boolean,
removed: number,
removable: boolean,
};
export type DisputeEvidenceImageData = {
ulid: string,
url: string | null,
thumbUrl: string | null,
};
export type DisputePartyData = {
ulid: string,
username: string,
roleLabel: string,
statusLabel: string,
statusTone: string,
joinedAt: string,
verifiedAccounts: number,
deleted: boolean,
priorDisputes: App.Domain.PlayerAccounts.Data.DisputePriorData[],
};
export type DisputePriorData = {
ulid: string,
tag: string,
side: string,
status: App.Domain.PlayerAccounts.Enums.DisputeStatus,
statusLabel: string,
openedAt: string,
reviewable: boolean,
};
export type DisputeQueueData = {
entries: App.Domain.PlayerAccounts.Data.DisputeQueueRowData[],
nextCursor: string | null,
previousCursor: string | null,
};
export type DisputeQueueRowData = {
ulid: string,
tag: string,
claimant: string,
holder: string | null,
status: App.Domain.PlayerAccounts.Enums.DisputeStatus,
statusLabel: string,
waitingSince: string,
openedAt: string,
assignedTo: string | null,
blockedReason: string | null,
};
export type DisputeResultData = {
disputeUlid: string | null,
status: App.Domain.PlayerAccounts.Enums.DisputeStatus | null,
refusal: App.Domain.PlayerAccounts.Enums.DisputeRefusal | null,
};
export type DisputeReviewData = {
ulid: string,
tag: string,
status: App.Domain.PlayerAccounts.Enums.DisputeStatus,
statusLabel: string,
active: boolean,
openedAt: string,
waitingSince: string,
escalatedAt: string | null,
decidedAt: string | null,
decidedBy: string | null,
assignedTo: string | null,
decisionNote: string | null,
accountName: string,
accountStatus: App.Domain.PlayerAccounts.Enums.CocAccountStatus,
accountTownHall: number | null,
claimant: App.Domain.PlayerAccounts.Data.DisputePartyData,
holder: App.Domain.PlayerAccounts.Data.DisputePartyData | null,
evidence: App.Domain.PlayerAccounts.Data.DisputeEvidenceData[],
claims: App.Domain.PlayerAccounts.Data.DisputeClaimData[],
snapshots: App.Domain.PlayerAccounts.Data.DisputeSnapshotData[],
decisions: App.Domain.PlayerAccounts.Data.DisputeDecisionOptionData[],
blockedReason: string | null,
noteMax: number,
canReleaseTag: boolean,
releaseBlockedReason: string | null,
};
export type DisputeSnapshotData = {
capturedAt: string,
townHallLevel: number | null,
clanTag: string | null,
trophies: number | null,
changes: string[],
};
export type OwnCocAccountData = {
ulid: string,
tag: string,
name: string,
status: App.Domain.PlayerAccounts.Enums.CocAccountStatus,
statusLabel: string,
townHallLevel: number | null,
featured: boolean,
canFeature: boolean,
};
export type PartyDisputeData = {
ulid: string,
tag: string,
role: App.Domain.PlayerAccounts.Enums.DisputeParty,
status: App.Domain.PlayerAccounts.Enums.DisputeStatus,
statusLabel: string,
waitingOn: string | null,
deadline: string | null,
openedAt: string,
closedAt: string | null,
outcome: App.Domain.PlayerAccounts.Enums.PartyDisputeOutcome | null,
outcomeLabel: string | null,
accountUlid: string | null,
submissions: App.Domain.PlayerAccounts.Data.PartySubmissionData[],
evidenceLeft: number,
canRespond: boolean,
canWithdraw: boolean,
canRelease: boolean,
canVerify: boolean,
withdrawCountsTowardBar: boolean,
};
export type PartySubmissionData = {
note: string | null,
images: App.Domain.PlayerAccounts.Data.DisputeEvidenceImageData[],
at: string,
opening: boolean,
removed: number,
};
export type PendingDisputesData = {
awaitingAdmin: number,
oldestWaitingSince: string | null,
pastHolderWindow: number,
running: number,
};
export type PlayerCardData = {
ulid: string,
tag: string,
name: string,
status: App.Domain.PlayerAccounts.Enums.CocAccountStatus,
statusLabel: string,
townHallLevel: number | null,
townHall: App.Domain.GameAssets.Data.GameAssetData | null,
builderHallLevel: number | null,
xpLevel: number | null,
trophies: number | null,
bestTrophies: number | null,
warStars: number | null,
leagueName: string | null,
league: App.Domain.GameAssets.Data.GameAssetData | null,
clan: App.Domain.PlayerAccounts.Data.AccountClanData | null,
clanHidden: boolean,
featured: boolean,
stale: boolean,
syncedAt: string | null,
syncedAgeSeconds: number | null,
};
export type ProfileAccountsData = {
cards: App.Domain.PlayerAccounts.Data.PlayerCardData[],
featured: App.Domain.PlayerAccounts.Data.PlayerCardData | null,
warStars: number | null,
verified: boolean,
};
export type ProgressionGroupData = {
key: string,
label: string,
village: App.Domain.GameAssets.Enums.Village,
units: App.Domain.PlayerAccounts.Data.ProgressionUnitData[],
};
export type ProgressionUnitData = {
name: string,
asset: App.Domain.GameAssets.Data.GameAssetData,
level: number,
maxLevel: number | null,
maxed: boolean,
locked: boolean,
equipment: App.Domain.PlayerAccounts.Data.ProgressionUnitData[],
};
export type VerifyResultData = {
outcome: App.Domain.PlayerAccounts.Enums.VerifyOutcome,
accountUlid: string | null,
superseded: boolean,
featured: boolean,
retryAfter: number | null,
};
}
namespace Enums {
export type AttachBlock = 'email_unverified' | 'account_blocked';
export type AttachOutcome = 'ready' | 'attached' | 'already_attached' | 'not_found' | 'verified_elsewhere' | 'unavailable' | 'rate_limited' | 'tag_suspended';
export type ClaimFailureReason = 'invalid_token' | 'already_claimed' | 'api_error' | 'rate_limited' | 'not_found';
export type ClaimMethod = 'api_token' | 'dispute' | 'admin';
export type ClaimStatus = 'pending' | 'succeeded' | 'failed' | 'rejected' | 'superseded';
export type CocAccountStatus = 'unverified' | 'verified' | 'disputed' | 'suspended' | 'released';
export type DisputeDecision = 'transfer' | 'deny' | 'suspend' | 'ask_claimant' | 'ask_holder';
export type DisputeParty = 'claimant' | 'holder';
export type DisputeQueueView = 'active' | 'awaiting_admin' | 'waiting_on_holder' | 'waiting_on_claimant' | 'closed';
export type DisputeRefusal = 'not_held' | 'own_account' | 'already_disputed' | 'tag_suspended' | 'too_many_open' | 'too_many_today' | 'too_many_tags' | 'barred' | 'not_your_turn' | 'closed' | 'holder_cannot_keep' | 'claimant_unavailable' | 'recently_withdrawn' | 'not_suspended';
export type DisputeStatus = 'open' | 'awaiting_admin' | 'awaiting_claimant' | 'awaiting_holder' | 'resolved_transfer' | 'resolved_denied' | 'resolved_suspended' | 'withdrawn' | 'auto_resolved';
export type PartyDisputeOutcome = 'transferred_to_you' | 'released_to_you' | 'transferred_away' | 'released_by_you' | 'denied' | 'denied_token' | 'kept' | 'kept_token' | 'suspended' | 'withdrawn_by_you' | 'withdrawn' | 'withdrawn_inactive' | 'verified_by_token';
export type RefreshOutcome = 'updated' | 'background' | 'not_found' | 'unavailable' | 'cooling_down' | 'too_many_refreshes';
export type ReleaseReason = 'detach' | 'deletion' | 'ban' | 'admin';
export type SnapshotSource = 'scheduled' | 'manual' | 'verification';
export type SyncOutcome = 'changed' | 'unchanged' | 'not_found' | 'failed' | 'postponed' | 'skipped';
export type VerificationMethod = 'api_token' | 'admin';
export type VerifyOutcome = 'verified' | 'invalid_token' | 'not_found' | 'unavailable' | 'rate_limited' | 'tag_suspended';
}
}
namespace Users {
namespace Data {
export type AvatarData = {
status: App.Domain.Media.Enums.MediaStatus | null,
url512: string | null,
url128: string | null,
url48: string | null,
};
export type CodeLabelData = {
code: string,
label: string,
};
export type PrivacyFormData = {
visibility: App.Domain.Users.Enums.ProfileVisibility,
showCocAccounts: boolean,
showClan: boolean,
allowRecruitmentContact: boolean,
searchable: boolean,
};
export type ProfileFormData = {
username: string,
displayName: string | null,
bio: string | null,
countryCode: string | null,
languages: string[],
timezone: string | null,
socials: App.Domain.Users.Data.SocialHandlesData,
avatar: App.Domain.Users.Data.AvatarData,
};
export type ProfileStatsData = {
basesPublished: number,
likesReceived: number,
copies: number,
};
export type PublicProfileData = {
username: string,
displayName: string | null,
avatarUrl512: string | null,
avatarUrl128: string | null,
bio: string | null,
country: App.Domain.Users.Data.CodeLabelData | null,
languages: App.Domain.Users.Data.CodeLabelData[],
socials: App.Domain.Users.Data.SocialLinkData[],
memberSince: string,
stats: App.Domain.Users.Data.ProfileStatsData,
isOwn: boolean,
};
export type SocialHandlesData = {
youtube: string | null,
twitch: string | null,
x: string | null,
discord: string | null,
};
export type SocialLinkData = {
network: string,
label: string,
handle: string,
url: string | null,
};
export type VisibilityOptionData = {
value: App.Domain.Users.Enums.ProfileVisibility,
label: string,
description: string,
};
}
namespace Enums {
export type ProfileVisibility = 'public' | 'members' | 'private';
}
}
}
namespace Http {
namespace Data {
export type AuthData = {
user: App.Http.Data.AuthUserData | null,
can: Record<string, boolean>,
};
export type AuthUserData = {
username: string,
avatarUrl: string | null,
emailVerified: boolean,
};
export type CocApiNoticeData = {
reason: App.Domain.CocIntegration.Enums.CocCircuitReason,
};
export type SharedPropsData = {
auth: App.Http.Data.AuthData,
flash: {
success: string | null,
error: string | null,
},
unreadCount: number | null,
features: Record<string, boolean>,
cocApi: App.Http.Data.CocApiNoticeData | null,
};
namespace Account {
export type AccountStatusPageData = {
status: App.Domain.Auth.Enums.UserStatus,
reason: string | null,
endsAt: string | null,
};
}
namespace Accounts {
export type AttachPageData = {
tag: string | null,
preview: App.Domain.PlayerAccounts.Data.AttachResultData | null,
verifyResult: App.Domain.PlayerAccounts.Data.VerifyResultData | null,
block: App.Domain.PlayerAccounts.Enums.AttachBlock | null,
disputeUlid: string | null,
};
export type VerifiedPageData = {
account: App.Domain.PlayerAccounts.Data.OwnCocAccountData,
firstAccount: boolean,
profileUsername: string,
};
export type VerifyPageData = {
account: App.Domain.PlayerAccounts.Data.OwnCocAccountData,
result: App.Domain.PlayerAccounts.Data.VerifyResultData | null,
};
}
namespace Admin {
export type AdminDashboardPageData = {
platformStats: boolean,
disputes: boolean,
};
export type AdminDisputeIndexPageData = {
view: string,
mine: boolean,
views: App.Http.Data.Admin.FilterOptionData[],
};
export type AdminDisputeShowPageData = {
dispute: App.Domain.PlayerAccounts.Data.DisputeReviewData,
claimantSanctions: App.Domain.Moderation.Data.SanctionData[],
holderSanctions: App.Domain.Moderation.Data.SanctionData[],
auditTrail: App.Http.Data.Admin.AuditTrailEntryData[],
moreAuditEntries: boolean,
};
export type AdminUserFiltersData = {
search: string | null,
role: string | null,
status: string | null,
};
export type AdminUserIndexPageData = {
filters: App.Http.Data.Admin.AdminUserFiltersData,
roles: App.Http.Data.Admin.FilterOptionData[],
statuses: App.Http.Data.Admin.FilterOptionData[],
};
export type AdminUserListData = {
entries: App.Domain.Auth.Data.AdminUserRowData[],
newerCursor: string | null,
olderCursor: string | null,
};
export type AdminUserShowPageData = {
user: App.Domain.Auth.Data.AdminUserDetailData,
displayName: string | null,
avatarUrl: string | null,
auditTrail: App.Http.Data.Admin.AuditTrailEntryData[],
moreAuditEntries: boolean,
sanctions: App.Domain.Moderation.Data.SanctionAbilitiesData,
sanctionHistory: App.Domain.Moderation.Data.SanctionData[],
sanctionForm: App.Http.Data.Admin.SanctionFormData,
};
export type AuditLogEntryData = {
id: number,
action: string,
actionLabel: string,
actorUsername: string | null,
actorRoleLabel: string | null,
actorVia: string | null,
subjectLabel: string,
subjectName: string | null,
before: Record<string, any> | null,
after: Record<string, any> | null,
context: Record<string, any>,
userAgent: string | null,
requestId: string | null,
createdAt: string,
};
export type AuditLogFiltersData = {
actor: string | null,
target: string | null,
action: string | null,
from: string | null,
to: string | null,
};
export type AuditLogListData = {
entries: App.Http.Data.Admin.AuditLogEntryData[],
newerCursor: string | null,
olderCursor: string | null,
};
export type AuditLogPageData = {
filters: App.Http.Data.Admin.AuditLogFiltersData,
actions: App.Http.Data.Admin.FilterOptionData[],
};
export type AuditTrailEntryData = {
id: number,
actionLabel: string,
actorUsername: string | null,
actorRoleLabel: string | null,
actorVia: string | null,
before: Record<string, any> | null,
after: Record<string, any> | null,
createdAt: string,
};
export type FilterOptionData = {
value: string,
label: string,
};
export type SanctionFormData = {
reasons: App.Http.Data.Admin.FilterOptionData[],
maxDays: number,
publicReasonMax: number,
noteMax: number,
};
}
namespace Auth {
export type ConfirmPasswordPageData = {
minutes: number,
};
export type ForgotPasswordPageData = {
status: string | null,
turnstileSiteKey: string | null,
};
export type LoginPageData = {
status: string | null,
};
export type RegisterPageData = {
usernameMin: number,
usernameMax: number,
passwordMin: number,
turnstileSiteKey: string | null,
formStarted: string,
};
export type RegisterSentPageData = {
linkMinutes: number,
};
export type ResetPasswordPageData = {
token: string,
email: string,
};
export type VerificationResultPageData = {
outcome: string,
message: string,
signedIn: boolean,
username: string | null,
confirmUrl: string | null,
};
export type VerifyEmailPageData = {
email: string,
status: string | null,
linkMinutes: number,
};
}
namespace Disputes {
export type DisputeCreatePageData = {
tag: string,
player: App.Domain.PlayerAccounts.Data.CocPlayerPreviewData | null,
refusal: string | null,
evidenceUpload: App.Domain.Media.Data.UploadCollectionData,
evidenceMax: number,
textMax: number,
responseDays: number,
};
export type DisputeShowPageData = {
dispute: App.Domain.PlayerAccounts.Data.PartyDisputeData,
evidenceUpload: App.Domain.Media.Data.UploadCollectionData,
textMax: number,
};
}
namespace Notifications {
export type NotificationIndexPageData = {
category: string | null,
tabs: App.Http.Data.Notifications.NotificationTabData[],
notifications: App.Domain.Notifications.Data.NotificationSliceData,
};
export type NotificationTabData = {
value: string | null,
label: string,
};
export type UnsubscribePageData = {
outcome: string,
message: string,
confirmUrl: string | null,
};
}
namespace Profile {
export type ProfileShowPageData = {
profile: App.Domain.Users.Data.PublicProfileData,
accounts: App.Domain.PlayerAccounts.Data.ProfileAccountsData,
};
}
namespace Settings {
export type DangerZonePageData = {
graceDays: number,
canRequestDeletion: boolean,
holds: string[],
};
export type EmailChangeConfirmPageData = {
outcome: string,
message: string,
username: string | null,
newEmail: string | null,
confirmUrl: string | null,
};
export type EmailPreferencesPageData = {
settings: App.Domain.Notifications.Data.EmailPreferencesData,
};
export type PrivacySettingsPageData = {
settings: App.Domain.Users.Data.PrivacyFormData,
visibilityOptions: App.Domain.Users.Data.VisibilityOptionData[],
username: string,
};
export type ProfileSettingsPageData = {
profile: App.Domain.Users.Data.ProfileFormData,
username: App.Domain.Auth.Data.UsernameSettingsData,
avatarUpload: App.Domain.Media.Data.UploadCollectionData,
countries: {
value: string,
label: string,
}[],
languages: {
value: string,
label: string,
}[],
timezones: {
value: string,
label: string,
}[],
limits: {
displayNameMax: number,
bioMax: number,
languagesMax: number,
},
};
export type SecuritySettingsPageData = {
passwordMinLength: number,
email: string,
pendingEmail: string | null,
linkMinutes: number,
};
}
}
}
namespace Support {
namespace Health {
export type HealthStatus = 'ok' | 'degraded' | 'down' | 'unknown';
}
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}
