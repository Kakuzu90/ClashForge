import Verified from '@/Pages/Accounts/Verified.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

const sent = vi.hoisted(() => [] as { method: string; url: string }[]);

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/accounts/x/verified', props: {} }),
    useForm: () => reactive({ processing: false, errors: {}, put: (url: string) => sent.push({ method: 'put', url }) }),
}));

vi.mock('@/Composables/useToast', () => ({ useToast: () => ({ push: vi.fn() }) }));

afterEach(() => {
    sent.length = 0;
});

type Account = App.Domain.PlayerAccounts.Data.OwnCocAccountData;
const account = (overrides: Partial<Account> = {}): Account => ({
    ulid: '01J0000000000000000000CARD',
    tag: '#2PQ8GRJC',
    name: 'Fixture Chief',
    status: 'verified',
    statusLabel: 'Verified',
    townHallLevel: 16,
    featured: false,
    canFeature: true,
    ...overrides,
});

const render = (props: Partial<Account> = {}) =>
    mount(Verified, { props: { account: account(props), firstAccount: false, profileUsername: 'chief' }, global: { stubs: { AppLayout: true } } });

describe('Accounts/Verified', () => {
    it('offers to make a second verified account the featured one (specs/18 §6, P2-14)', async () => {
        const wrapper = render();
        const button = wrapper.findAll('button').find((b) => b.text() === 'Make this your featured account');

        expect(wrapper.text()).toContain('Your featured account is listed first on your profile.');
        await button!.trigger('click');
        expect(sent).toEqual([{ method: 'put', url: '/accounts/01J0000000000000000000CARD/featured' }]);
    });

    it('says so instead when the account is already featured', () => {
        const text = render({ featured: true, canFeature: false }).text();

        expect(text).toContain('This is now your featured account.');
        expect(text).not.toContain('Make this your featured account');
    });

    it('offers nothing the server did not allow', () => {
        expect(render({ canFeature: false }).text()).not.toContain('Make this your featured account');
    });
});
