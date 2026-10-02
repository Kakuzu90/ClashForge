import type { PillTone } from '@/Components/ui/UiPill.vue';

/**
 * The Town Hall tier ramp (specs/18 §3): the pill tone for a TH level, always shown with its numeral.
 */
export function thTone(level: number): PillTone {
    if (level >= 17) return 'th-7';
    if (level >= 15) return 'th-6';
    if (level >= 13) return 'th-5';
    if (level >= 11) return 'th-4';
    if (level >= 8) return 'th-3';
    if (level >= 5) return 'th-2';
    return 'th-1';
}
