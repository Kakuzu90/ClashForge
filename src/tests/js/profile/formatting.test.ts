import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import { formatMonthYear } from '@/Composables/useDateTime';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

// Both run during SSR and again on hydration, so the output must not depend on the runtime's
// locale or timezone.
describe('server-safe formatting', () => {
    it('formats member-since in English, in UTC', () => {
        expect(formatMonthYear('2026-09-30T23:30:00+00:00')).toBe('September 2026');
        expect(formatMonthYear('2026-10-01T00:30:00+02:00')).toBe('September 2026');
    });

    it('groups stat numbers the same way everywhere and keeps the label first in reading order', () => {
        const wrapper = mount(UiStatBlock, { props: { value: 1250000, label: 'Likes received' } });

        expect(wrapper.get('dd').text()).toBe('1,250,000');
        expect(wrapper.element.firstElementChild?.tagName).toBe('DT');
    });
});
