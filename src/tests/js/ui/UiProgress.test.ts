import UiProgress from '@/Components/ui/UiProgress.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiProgress', () => {
    it('exposes a labelled, clamped determinate value', () => {
        const wrapper = mount(UiProgress, { props: { label: 'Uploading base.jpg', value: 140 } });
        const bar = wrapper.get('[role="progressbar"]');

        expect(bar.attributes('aria-valuenow')).toBe('100');
        expect(wrapper.get(`#${bar.attributes('aria-labelledby')}`).text()).toBe('Uploading base.jpg');
        expect(wrapper.text()).toContain('100%');
    });

    it('omits aria-valuenow when indeterminate', () => {
        const wrapper = mount(UiProgress, { props: { label: 'Processing' } });

        expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBeUndefined();
        expect(wrapper.text()).not.toContain('%');
    });
});
