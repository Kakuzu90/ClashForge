import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiStatBlock', () => {
    it('formats the value with a fixed locale', () => {
        expect(mount(UiStatBlock, { props: { value: 5124, label: 'Trophies' } }).get('dd').text()).toBe('5,124');
    });

    it('shows a rising delta in green and a falling one in red, with what it compares', () => {
        const up = mount(UiStatBlock, { props: { value: 5124, label: 'Trophies', delta: 142, deltaLabel: 'Change over the last 7 days' } });
        const down = mount(UiStatBlock, { props: { value: 1480, label: 'War stars', delta: -1030 } });

        expect(up.get('dd').text()).toContain('+142');
        expect(up.get('dd').text()).toContain('Change over the last 7 days:');
        expect(up.html()).toContain('text-success-fg');
        expect(down.get('dd').text()).toContain('-1,030');
        expect(down.html()).toContain('text-danger-fg');
    });

    it('hides the chip for no change and reads a missing value as not available', () => {
        expect(mount(UiStatBlock, { props: { value: 10, label: 'XP level', delta: 0 } }).get('dd').text()).toBe('10');
        expect(mount(UiStatBlock, { props: { value: null, label: 'XP level', delta: 3 } }).get('dd').text()).toContain('Not available');
    });
});
