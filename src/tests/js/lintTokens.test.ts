import { describe, expect, it } from 'vitest';
// @ts-expect-error plain ESM script without types
import { findViolations } from '../../scripts/lint-tokens.mjs';

describe('token lint', () => {
    it('flags arbitrary colour values', () => {
        expect(findViolations('<div class="bg-[#fff] text-[rgb(0,0,0)] hover:border-[hsl(0_0%_0%)]">')).toHaveLength(3);
    });

    it('allows tokens and non-colour arbitrary values', () => {
        expect(findViolations('<div class="bg-surface text-fg-muted w-[42px] bg-[length:200%_100%]">')).toEqual([]);
    });
});
