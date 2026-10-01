/**
 * A whole count with digit grouping, e.g. "1,204".
 */
export function formatCount(value: number, locale?: string): string {
    return new Intl.NumberFormat(locale).format(value);
}

const UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte'] as const;

/**
 * A storage size in decimal units (1 GB = 1000 MB, as the bucket bills it), e.g. "1.4 GB".
 */
export function formatBytes(bytes: number, locale?: string): string {
    let value = Math.max(0, bytes);
    let unit = 0;
    while (value >= 1000 && unit < UNITS.length - 1) {
        value /= 1000;
        unit++;
    }

    return new Intl.NumberFormat(locale, {
        style: 'unit',
        unit: UNITS[unit],
        unitDisplay: 'short',
        maximumFractionDigits: unit === 0 ? 0 : 1,
    }).format(value);
}
