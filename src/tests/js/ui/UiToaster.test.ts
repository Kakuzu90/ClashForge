import UiToaster from '@/Components/ui/UiToaster.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiToaster', () => {
    it('is a labelled region', () => {
        const region = mount(UiToaster).get('[role="region"]');
        expect(region.attributes('aria-label')).toBe('Notifications');
    });

    it('sits above the mobile tab bar when asked', () => {
        expect(mount(UiToaster, { props: { aboveTabBar: true } }).get('[role="region"]').classes()).toContain('bottom-[calc(56px+env(safe-area-inset-bottom))]');
        expect(mount(UiToaster).get('[role="region"]').classes()).toContain('bottom-0');
    });
});
