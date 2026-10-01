import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

let user: { username: string } | null = null;

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/', props: { auth: { user, can: {} } } }),
}));

import Index from '@/Pages/Home/Index.vue';

describe('Home/Index', () => {
    it('renders the product name as the only h1', () => {
        const wrapper = mount(Index);
        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.get('h1').text()).toBe('Clash Commons');
    });

    it('offers guests the way to create an account, and hides it once signed in', () => {
        user = null;
        expect(mount(Index).get('a[href="/register"]').text()).toBe('Create your account');

        user = { username: 'chief' };
        expect(mount(Index).find('a[href="/register"]').exists()).toBe(false);
    });
});
