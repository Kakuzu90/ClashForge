declare namespace App {
namespace Domain {
namespace Audit {
namespace Enums {
export type AuditAction = 'role.changed' | 'sanction.applied' | 'sanction.lifted' | 'sanction.expired' | 'user.anonymised';
export type AuditSubject = 'user';
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
}
namespace Enums {
export type EmailChangeOutcome = 'pending' | 'changed' | 'already_changed' | 'taken' | 'wrong_account' | 'invalid';
export type EmailVerificationOutcome = 'pending' | 'verified' | 'already_verified' | 'invalid';
export type Role = 'user' | 'moderator' | 'admin' | 'super_admin';
export type StaffAbility = 'access-admin' | 'view-users' | 'view-platform-stats' | 'view-report-queue' | 'claim-report-case' | 'hide-content' | 'remove-content' | 'warn-user' | 'restrict-user' | 'suspend-user' | 'ban-user' | 'lift-sanction' | 'review-media-quarantine' | 'resolve-disputes' | 'force-ownership-transfer' | 'approve-sellers' | 'resolve-marketplace-disputes' | 'manage-tags' | 'view-moderation-log' | 'view-audit-log' | 'manage-roles' | 'manage-settings' | 'hard-delete-user' | 'impersonate';
export type UserStatus = 'active' | 'restricted' | 'suspended' | 'banned' | 'pending_deletion';
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
export type GameAssetCategory = 'troop' | 'hero' | 'spell' | 'equipment' | 'town_hall' | 'league';
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
export type ModerationActionType = 'hide' | 'unhide' | 'remove' | 'restore' | 'warn' | 'restrict' | 'suspend' | 'ban' | 'lift' | 'unban' | 'dismiss' | 'escalate' | 'transfer_ownership' | 'approve_seller' | 'reject_listing';
export type ReasonCode = 'account_trading' | 'scam' | 'false_ownership' | 'nsfw' | 'hate' | 'harassment' | 'impersonation' | 'stolen_content' | 'off_platform_payment' | 'spam' | 'wrong_category' | 'other';
export type SanctionType = 'warning' | 'restriction' | 'suspension' | 'ban';
}
}
namespace Notifications {
namespace Data {
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
};
}
namespace Enums {
export type NotificationCategory = 'security' | 'ownership' | 'bases' | 'moderation' | 'recruitment' | 'marketplace' | 'social' | 'staff';
export type NotificationType = 'email_verified' | 'password_changed' | 'new_device_sign_in' | 'account_suspended' | 'account_banned' | 'sanction_ended' | 'media_processing_failed';
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
export type SharedPropsData = {
auth: App.Http.Data.AuthData,
flash: {
success: string | null,
error: string | null,
},
unreadCount: number | null,
features: Record<string, boolean>,
};
namespace Account {
export type AccountStatusPageData = {
status: App.Domain.Auth.Enums.UserStatus,
reason: string | null,
endsAt: string | null,
};
}
namespace Admin {
export type AdminDashboardPageData = {
platformStats: boolean,
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
}
namespace Profile {
export type ProfileShowPageData = {
profile: App.Domain.Users.Data.PublicProfileData,
};
}
namespace Settings {
export type DangerZonePageData = {
graceDays: number,
canRequestDeletion: boolean,
};
export type EmailChangeConfirmPageData = {
outcome: string,
message: string,
username: string | null,
newEmail: string | null,
confirmUrl: string | null,
};
export type PrivacySettingsPageData = {
settings: App.Domain.Users.Data.PrivacyFormData,
visibilityOptions: App.Domain.Users.Data.VisibilityOptionData[],
username: string,
};
export type ProfileSettingsPageData = {
profile: App.Domain.Users.Data.ProfileFormData,
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
export type FailedJobClassData = {
name: string | null,
count: number,
lastFailedAt: string,
};
export type FailedJobsSummaryData = {
lastHour: number,
last24Hours: number,
alertPerHour: number,
overThreshold: boolean,
topClasses: App.Support.Health.FailedJobClassData[],
};
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
