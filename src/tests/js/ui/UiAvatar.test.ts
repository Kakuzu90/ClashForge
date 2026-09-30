import UiAvatar from '@/Components/ui/UiAvatar.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiAvatar', () => {
    it('falls back to initials when there is no image', () => {
        const wrapper = mount(UiAvatar, { props: { name: 'Chief Pekka' } });
        expect(wrapper.get('[role="img"]').text()).toBe('CP');
        expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Chief Pekka');
    });

    it('falls back to initials when the image fails', async () => {
        const wrapper = mount(UiAvatar, { props: { name: 'Solo', src: '/broken.png' } });
        await wrapper.get('img').trigger('error');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('[role="img"]').text()).toBe('S');
    });
});

describe('UiAvatar typography', () => {
    it('uses the body font below 48px and never goes under 12px', () => {
        const wrapper = mount(UiAvatar, { props: { name: 'Tiny', size: 24 } });
        expect(wrapper.classes()).toContain('font-body');
        expect(wrapper.attributes('style')).toContain('font-size: 12px');
    });

    it('announces the loading label', () => {
        const wrapper = mount(UiAvatar, { props: { name: 'X', loading: true, loadingLabel: 'Loading avatar' } });
        expect(wrapper.get('[role="status"]').text()).toBe('Loading avatar');
    });
});
