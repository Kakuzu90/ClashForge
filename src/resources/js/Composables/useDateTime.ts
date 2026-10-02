/**
 * A date and time in the viewer's own locale and timezone, e.g. for when a sanction ends. Call it
 * only on pages rendered in the browser (SSR is off for them), so server and client cannot differ.
 */
export function formatDateTime(iso: string, locale?: string): string {
    return new Intl.DateTimeFormat(locale, { dateStyle: 'long', timeStyle: 'short' }).format(new Date(iso));
}

/**
 * "September 2026", identical on the server and in the browser (fixed locale and UTC), so it is
 * safe on server-rendered pages.
 */
export function formatMonthYear(iso: string): string {
    return new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(iso));
}

/**
 * A length of time in its two largest units, e.g. how long a job has waited: "45 s", "12 min",
 * "3 h 5 min", "2 d 4 h".
 */
export function formatDuration(seconds: number): string {
    const s = Math.max(0, Math.floor(seconds));
    const units: [number, string][] = [
        [86400, 'd'],
        [3600, 'h'],
        [60, 'min'],
    ];

    for (const [i, [size, label]] of units.entries()) {
        if (s >= size) {
            const whole = Math.floor(s / size);
            const [nextSize, nextLabel] = units[i + 1] ?? [1, 's'];
            const rest = Math.floor((s % size) / nextSize);

            return rest > 0 && size > 60 ? `${whole} ${label} ${rest} ${nextLabel}` : `${whole} ${label}`;
        }
    }

    return `${s} s`;
}
