declare namespace App {
namespace Domain {
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
namespace Auth {
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
