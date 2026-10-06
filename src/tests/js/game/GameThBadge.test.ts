import GameThBadge from '@/Components/game/GameThBadge.vue';
import { thTier, thTone } from '@/Composables/useThTier';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('Town Hall tier ramp', () => {
    it.each([
        [1, 1],
        [4, 1],
        [5, 2],
        [7, 2],
        [8, 3],
        [10, 3],
        [11, 4],
        [12, 4],
        [13, 5],
        [14, 5],
        [15, 6],
        [16, 6],
        [17, 7],
        [18, 7],
        [30, 7],
    ])('puts TH %i in tier %i', (level, tier) => {
        expect(thTier(level)).toBe(tier);
        expect(thTone(level)).toBe(`th-${tier}`);
    });
});

describe('GameThBadge', () => {
    it('always shows the numeral and names the level', () => {
        const badge = mount(GameThBadge, { props: { level: 16 } });

        expect(badge.text()).toBe('16');
        expect(badge.attributes('aria-label')).toBe('Town Hall 16');
        expect(badge.html()).toContain('stroke-th-6');
    });

    it('names a Builder Hall and rings the top tier', () => {
        expect(mount(GameThBadge, { props: { level: 10, builder: true } }).attributes('aria-label')).toBe('Builder Hall 10');
        expect(mount(GameThBadge, { props: { level: 17 } }).html()).toContain('stroke-brand');
        expect(mount(GameThBadge, { props: { level: 16 } }).html()).not.toContain('stroke-brand');
    });

    it('shows the Town Hall image only at lg, and reads the same without it', () => {
        const asset = { kind: 'town_hall' as const, url: 'https://cdn.test/th16.png', alt: 'Town Hall 16', short: '16', width: 64, height: 64 };

        expect(
            mount(GameThBadge, { props: { level: 16, size: 'lg', asset } })
                .find('img')
                .exists(),
        ).toBe(true);
        expect(
            mount(GameThBadge, { props: { level: 16, size: 'md', asset } })
                .find('img')
                .exists(),
        ).toBe(false);
        expect(mount(GameThBadge, { props: { level: 16, size: 'lg', asset: { ...asset, url: null } } }).text()).toBe('16');
    });
});
