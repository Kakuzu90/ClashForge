import AccountControls from '@/Components/shell/AccountControls.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: (...args: unknown[]) => post(...args) },
    usePage: () => ({
        url: '/',
        props: { auth: { user: { username: 'chief', avatarUrl: null, emailVerified: true }, can: {} }, flash: {}, unreadCount: null },
    }),
}));

describe('AccountControls', () => {
    it('opens an account menu with your profile, settings and sign out', async () => {
        const wrapper = mount(AccountControls, { attachTo: document.body });
        const trigger = wrapper.get('button[aria-label="chief, account menu"]');

        expect(trigger.attributes('aria-expanded')).toBe('false');
        await trigger.trigger('click');

        const items = wrapper.findAll('[role="menuitem"]');
        expect(items.map((item) => item.text())).toEqual(['Your profile', 'Settings', 'Sign out']);
        expect(items[0]!.attributes('href')).toBe('/u/chief');
        expect(items[1]!.attributes('href')).toBe('/settings/profile');

        await items[2]!.trigger('click');
        expect(post).toHaveBeenCalledWith('/logout', {}, expect.any(Object));
        wrapper.unmount();
    });
});
