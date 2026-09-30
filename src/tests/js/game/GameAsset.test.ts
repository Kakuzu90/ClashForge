import GameAsset from '@/Components/game/GameAsset.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

type Asset = App.Domain.GameAssets.Data.GameAssetData;

const loaded: Asset = { kind: 'unit', url: 'https://cdn.test/sample.png', alt: 'Archer Queen', short: 'AQ', width: 256, height: 256 };
const fallback: Asset = { kind: 'town_hall', url: null, alt: 'Town Hall 16', short: '16', width: null, height: null };

describe('GameAsset', () => {
    it('renders the resolved image unmodified at the requested size', () => {
        const img = mount(GameAsset, { props: { asset: loaded, size: 48 } }).get('img');

        expect(img.attributes('src')).toBe(loaded.url);
        expect(img.attributes('alt')).toBe('Archer Queen');
        expect(img.attributes('width')).toBe('48');
        expect(img.attributes('height')).toBe('48');
        expect(img.attributes('loading')).toBe('lazy');
        // Scaling only: nothing that crops, borders or filters the asset.
        expect(img.classes().some((c) => /^(rounded|border|ring|filter|grayscale|blur|opacity|mix-blend)/.test(c))).toBe(false);
    });

    it('can load eagerly above the fold', () => {
        expect(
            mount(GameAsset, { props: { asset: loaded, lazy: false } })
                .get('img')
                .attributes('loading'),
        ).toBeUndefined();
    });

    it('shows our placeholder with an accessible name when there is no URL', () => {
        const wrapper = mount(GameAsset, { props: { asset: fallback, size: 32 } });
        const placeholder = wrapper.get('[role="img"]');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(placeholder.attributes('aria-label')).toBe('Town Hall 16');
        expect(placeholder.text()).toBe('16');
        expect(placeholder.attributes('style')).toContain('width: 32px');
    });

    it('falls back to the placeholder when the image fails to load, and recovers on a new URL', async () => {
        const wrapper = mount(GameAsset, { props: { asset: loaded } });

        await wrapper.get('img').trigger('error');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Archer Queen');

        await wrapper.setProps({ asset: { ...loaded, url: 'https://cdn.test/other.png' } });
        expect(wrapper.find('img').exists()).toBe(true);
    });

    it('uses a different placeholder shape per kind', () => {
        const paths = (['unit', 'town_hall', 'league', 'clan_badge'] as const).map((kind) =>
            mount(GameAsset, { props: { asset: { ...fallback, kind } } })
                .get('path')
                .attributes('d'),
        );

        expect(new Set(paths).size).toBe(4);
    });
});
