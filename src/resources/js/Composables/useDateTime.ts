/**
 * A date and time in the viewer's own locale and timezone, e.g. for when a sanction ends. Call it
 * only on pages rendered in the browser (SSR is off for them), so server and client cannot differ.
 */
export function formatDateTime(iso: string, locale?: string): string {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
}
