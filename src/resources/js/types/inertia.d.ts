import '@inertiajs/core';

// Shared props from HandleInertiaRequests (App\Http\Data\SharedPropsData) on every page.
declare module '@inertiajs/core' {
    interface PageProps extends App.Http.Data.SharedPropsData {
        /** Set by PageMeta::page(); the root view holds the rest of the meta. */
        meta?: { title: string | null };
    }
}
