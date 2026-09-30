import { describe, expect, it } from 'vitest';
// @ts-expect-error plain ESM script without types
import { findViolations } from '../../scripts/lint-game-assets.mjs';

describe('game asset lint', () => {
    it('flags game-asset hosts and pack paths', () => {
        expect(findViolations('<img src="https://api-assets.clashofclans.com/badges/200/x.png">')).toHaveLength(1);
        expect(findViolations('const u = `${cdn}/game/3/units/barbarian.png`;')).toHaveLength(2);
        expect(findViolations("fetch('/game/v2/manifest.json')")).toHaveLength(1);
        expect(findViolations('<img src="/img/townhalls/16.png">')).toHaveLength(1);
    });

    it('allows resolver output in props and ordinary words', () => {
        expect(findViolations('<GameAsset :asset="unit.icon" :size="32" />')).toEqual([]);
        expect(findViolations('<p>Game units and town halls load from the pack.</p>')).toEqual([]);
        expect(findViolations("import GameAsset from '@/Components/game/GameAsset.vue';")).toEqual([]);
    });
});
