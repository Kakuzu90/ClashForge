import AccountControls from '@/Components/shell/AccountControls.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: vi.fn() },
    usePage: () => ({
        url: '/',
        props: { auth: { user: { username: 'chief', avatarUrl: null, emailVerified: true }, can: {} }, flash: {}, unreadCount: null },
    }),
}));

describe('AccountControls', () => {
    it('links the avatar and name to your own public profile', () => {
        const link = mount(AccountControls).get('a[aria-label="chief, your profile"]');

        expect(link.attributes('href')).toBe('/u/chief');
    });
});
