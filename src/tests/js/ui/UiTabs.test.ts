import UiTabs from '@/Components/ui/UiTabs.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, ref } from 'vue';

const tabs = [
    { key: 'accounts', label: 'Accounts' },
    { key: 'bases', label: 'Bases' },
    { key: 'activity', label: 'Activity' },
];

function mountTabs(initial = 'accounts') {
    const Host = defineComponent({
        components: { UiTabs },
        setup: () => ({ tab: ref(initial), tabs }),
        template: `<UiTabs v-model="tab" :tabs="tabs" label="Profile sections">
            <template #accounts><p>Accounts panel</p></template>
            <template #bases><p>Bases panel</p></template>
            <template #activity><p>Activity panel</p></template>
        </UiTabs>`,
    });

    return mount(Host, { attachTo: document.body });
}

const selected = (wrapper: ReturnType<typeof mountTabs>) => wrapper.findAll('[role="tab"]').map((tab) => tab.attributes('aria-selected'));

describe('UiTabs', () => {
    it('wires tabs to panels with one tab stop', () => {
        const wrapper = mountTabs();
        const [first, second] = wrapper.findAll('[role="tab"]');

        expect(wrapper.get('[role="tablist"]').attributes('aria-label')).toBe('Profile sections');
        expect(first!.attributes('tabindex')).toBe('0');
        expect(second!.attributes('tabindex')).toBe('-1');

        const panel = wrapper.get(`#${first!.attributes('aria-controls')}`);
        expect(panel.attributes('role')).toBe('tabpanel');
        expect(panel.attributes('aria-labelledby')).toBe(first!.attributes('id'));
        expect(panel.isVisible()).toBe(true);
        wrapper.unmount();
    });

    it('activates a tab on click', async () => {
        const wrapper = mountTabs();
        await wrapper.findAll('[role="tab"]')[1]!.trigger('click');

        expect(selected(wrapper)).toEqual(['false', 'true', 'false']);
        expect(wrapper.text()).toContain('Bases panel');
        wrapper.unmount();
    });

    it('moves with the arrow keys, wrapping at the ends, and jumps with Home and End', async () => {
        const wrapper = mountTabs();
        const tab = (i: number) => wrapper.findAll('[role="tab"]')[i]!;

        await tab(0).trigger('keydown', { key: 'ArrowLeft' });
        expect(selected(wrapper)).toEqual(['false', 'false', 'true']);

        await tab(2).trigger('keydown', { key: 'ArrowRight' });
        expect(selected(wrapper)).toEqual(['true', 'false', 'false']);

        await tab(0).trigger('keydown', { key: 'End' });
        expect(selected(wrapper)).toEqual(['false', 'false', 'true']);

        await tab(2).trigger('keydown', { key: 'Home' });
        await wrapper.vm.$nextTick();
        expect(selected(wrapper)).toEqual(['true', 'false', 'false']);
        expect(document.activeElement).toBe(tab(0).element);
        wrapper.unmount();
    });

    it('falls back to the first tab when the model names none of them', () => {
        const wrapper = mountTabs('missing');

        expect(selected(wrapper)).toEqual(['true', 'false', 'false']);
        wrapper.unmount();
    });
});
