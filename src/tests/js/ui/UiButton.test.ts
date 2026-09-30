import UiButton from '@/Components/ui/UiButton.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ Link: { template: '<a><slot /></a>' } }));

afterEach(() => vi.restoreAllMocks());

describe('UiButton', () => {
    it('warns when icon-only has no accessible name', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        mount(UiButton, { props: { iconOnly: true }, slots: { default: '<svg />' } });
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('aria-label'));
    });

    it('does not warn when icon-only is labelled', () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        mount(UiButton, { props: { iconOnly: true }, attrs: { 'aria-label': 'Close' }, slots: { default: '<svg />' } });
        expect(warn).not.toHaveBeenCalled();
    });

    it('disables and keeps its label in place while loading', () => {
        const wrapper = mount(UiButton, { props: { loading: true }, slots: { default: 'Save' } });
        const button = wrapper.get('button');
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.attributes('aria-busy')).toBe('true');
        // The label stays in the accessibility tree (opacity, not visibility), so the name keeps "Save".
        expect(button.get('span.opacity-0').text()).toBe('Save');
        expect(wrapper.text()).toContain('Loading');
    });

    it('defaults to type="button" so it never submits by accident', () => {
        expect(mount(UiButton).get('button').attributes('type')).toBe('button');
    });
});
