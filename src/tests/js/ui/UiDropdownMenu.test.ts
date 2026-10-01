import UiDropdownMenu from '@/Components/ui/UiDropdownMenu.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

vi.mock('@inertiajs/vue3', () => ({ Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } }));

const items = [
    { key: 'profile', label: 'Your profile', href: '/u/chief' },
    { key: 'settings', label: 'Settings', href: '/settings/profile' },
    { key: 'sign-out', label: 'Sign out' },
];

function mountMenu() {
    return mount(UiDropdownMenu, { props: { items, label: 'Account menu' }, slots: { trigger: 'Menu' }, attachTo: document.body });
}

const focused = () => document.activeElement?.textContent?.trim();

describe('UiDropdownMenu', () => {
    it('opens on ArrowDown at the first item and on ArrowUp at the last', async () => {
        const wrapper = mountMenu();
        const trigger = wrapper.get('button[aria-haspopup="menu"]');

        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await nextTick();
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(trigger.attributes('aria-controls')).toBe(wrapper.get('[role="menu"]').attributes('id'));
        expect(focused()).toBe('Your profile');

        await wrapper.get('[role="menu"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);

        await trigger.trigger('keydown', { key: 'ArrowUp' });
        await nextTick();
        expect(focused()).toBe('Sign out');
        wrapper.unmount();
    });

    it('moves with the arrows, wrapping, and jumps with Home and End', async () => {
        const wrapper = mountMenu();
        await wrapper.get('button[aria-haspopup="menu"]').trigger('keydown', { key: 'Enter' });
        await nextTick();
        const menu = wrapper.get('[role="menu"]');

        await menu.trigger('keydown', { key: 'ArrowUp' });
        expect(focused()).toBe('Sign out');
        await menu.trigger('keydown', { key: 'ArrowDown' });
        expect(focused()).toBe('Your profile');
        await menu.trigger('keydown', { key: 'End' });
        expect(focused()).toBe('Sign out');
        await menu.trigger('keydown', { key: 'Home' });
        expect(focused()).toBe('Your profile');
        wrapper.unmount();
    });

    it('emits select for button items and closes on Tab or a click outside', async () => {
        const wrapper = mountMenu();
        const trigger = wrapper.get('button[aria-haspopup="menu"]');

        await trigger.trigger('click');
        await wrapper.findAll('[role="menuitem"]')[2]!.trigger('click');
        expect(wrapper.emitted('select')).toEqual([['sign-out']]);
        expect(wrapper.find('[role="menu"]').exists()).toBe(false);

        await trigger.trigger('click');
        await wrapper.get('[role="menu"]').trigger('keydown', { key: 'Tab' });
        expect(wrapper.find('[role="menu"]').exists()).toBe(false);

        await trigger.trigger('click');
        document.body.click();
        await nextTick();
        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        wrapper.unmount();
    });
});
