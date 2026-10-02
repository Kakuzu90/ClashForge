import type { PillTone } from '@/Components/ui/UiPill.vue';

export type ThTier = 1 | 2 | 3 | 4 | 5 | 6 | 7;

// The Town Hall tier ramp (specs/18 §3): the lowest TH level of each tier, top tier first. A new TH
// level above the top joins the top tier; a new tier is one line here (specs/23 §5).
const TIERS: [number, ThTier][] = [
    [17, 7],
    [15, 6],
    [13, 5],
    [11, 4],
    [8, 3],
    [5, 2],
];

/**
 * The tier for a TH level, always shown with its numeral: colour is decoration, the number is the
 * information.
 */
export function thTier(level: number): ThTier {
    return TIERS.find(([from]) => level >= from)?.[1] ?? 1;
}

/**
 * The pill tone for a TH level.
 */
export function thTone(level: number): PillTone {
    return `th-${thTier(level)}`;
}
