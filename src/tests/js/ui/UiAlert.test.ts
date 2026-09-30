import UiAlert from '@/Components/ui/UiAlert.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiAlert', () => {
    it('interrupts only for danger', () => {
        expect(mount(UiAlert, { props: { kind: 'danger' } }).attributes('role')).toBe('alert');

        for (const kind of ['info', 'success', 'warning'] as const) {
            expect(mount(UiAlert, { props: { kind } }).attributes('role')).toBe('status');
        }
    });

    it('announces the kind in text, not only by colour', () => {
        const wrapper = mount(UiAlert, { props: { kind: 'success' }, slots: { default: 'Link sent.' } });

        expect(wrapper.text()).toBe('Success: Link sent.');
    });
});
