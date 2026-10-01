import VerifyEmailBanner from '@/Components/shell/VerifyEmailBanner.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

let page = { url: '/', props: { auth: { user: null as { emailVerified: boolean } | null, can: {} } } };

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => page,
}));

const at = (url: string, user: { emailVerified: boolean } | null) => {
    page = { url, props: { auth: { user, can: {} } } };
    return mount(VerifyEmailBanner);
};

describe('VerifyEmailBanner', () => {
    it('shows an unverified account the way to a fresh link', () => {
        const wrapper = at('/settings/profile', { emailVerified: false });

        expect(wrapper.text()).toContain('Confirm your email');
        expect(wrapper.get('a').attributes('href')).toBe('/email/verify');
    });

    it('stays hidden for guests, verified accounts and on the confirm page itself', () => {
        expect(at('/', null).html()).not.toContain('Confirm your email');
        expect(at('/', { emailVerified: true }).html()).not.toContain('Confirm your email');
        expect(at('/email/verify?x=1', { emailVerified: false }).html()).not.toContain('Confirm your email');
    });
});
