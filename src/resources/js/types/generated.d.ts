declare namespace App {
namespace Domain {
namespace Auth {
namespace Data {
export type SessionData = {
key: string,
deviceLabel: string,
country: string | null,
lastActiveAt: string,
signedInAt: string | null,
isCurrent: boolean,
};
}
namespace Enums {
export type Role = 'user' | 'moderator' | 'admin' | 'super_admin';
export type StaffAbility = 'access-admin' | 'view-report-queue' | 'claim-report-case' | 'hide-content' | 'remove-content' | 'warn-user' | 'restrict-user' | 'suspend-user' | 'ban-user' | 'lift-sanction' | 'review-media-quarantine' | 'resolve-disputes' | 'force-ownership-transfer' | 'approve-sellers' | 'resolve-marketplace-disputes' | 'manage-tags' | 'view-moderation-log' | 'view-audit-log' | 'manage-roles' | 'manage-settings' | 'hard-delete-user' | 'impersonate';
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
namespace Auth {
export type ConfirmPasswordPageData = {
minutes: number,
};
export type ForgotPasswordPageData = {
status: string | null,
};
export type LoginPageData = {
status: string | null,
};
export type ResetPasswordPageData = {
token: string,
email: string,
};
}
namespace Profile {
export type ProfileShowPageData = {
profile: App.Domain.Users.Data.PublicProfileData,
};
}
namespace Settings {
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
