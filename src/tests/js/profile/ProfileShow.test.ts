import Show from '@/Pages/Profile/Show.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createSSRApp, h, nextTick } from 'vue';
import { renderToString } from 'vue/server-renderer';

type Listener = (event: { detail: { visit: Record<string, unknown> } }) => void;
const listeners: Record<string, Listener[]> = {};

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: {
        on: (name: string, callback: Listener) => {
            (listeners[name] ??= []).push(callback);
            return () => (listeners[name] = listeners[name]!.filter((l) => l !== callback));
        },
    },
    usePage: () => ({ url: '/u/chief', props: { auth: { user: null, can: {} } } }),
}));

type Profile = App.Domain.Users.Data.PublicProfileData;

const profile = (overrides: Partial<Profile> = {}): Profile => ({
    username: 'chief',
    displayName: 'Chief',
    avatarUrl512: null,
    avatarUrl128: null,
    bio: 'Builds war bases.',
    country: { code: 'DE', label: 'Germany' },
    languages: [{ code: 'en', label: 'English' }],
    socials: [
        { network: 'youtube', label: 'YouTube', handle: '@clashchief', url: 'https://www.youtube.com/@clashchief' },
        { network: 'discord', label: 'Discord', handle: 'clashchief', url: null },
    ],
    memberSince: '2026-09-15T10:00:00+00:00',
    stats: { basesPublished: 12, likesReceived: 3400, copies: 980 },
    isOwn: false,
    ...overrides,
});

const ssr = (props: Profile) => renderToString(createSSRApp({ render: () => h(Show, { profile: props }) }));

beforeEach(() => Object.keys(listeners).forEach((key) => delete listeners[key]));

describe('Profile/Show', () => {
    it('server-renders the cover, stats and member-since', async () => {
        const html = await ssr(profile());

        expect(html).toContain('<h1');
        expect(html).toContain('Chief');
        expect(html).toContain('@chief');
        expect(html).toContain('Germany');
        expect(html).toContain('<time datetime="2026-09-15T10:00:00+00:00">September 2026</time>');
        expect(html).toContain('3,400');
    });

    it('escapes markup in the bio and display name in the server output', async () => {
        const html = await ssr(profile({ bio: '<script>alert(1)</script><img src=x onerror=alert(1)>', displayName: '<svg onload=alert(1)>' }));

        expect(html).not.toContain('<script>alert(1)');
        expect(html).not.toContain('<img src=x');
        expect(html).not.toContain('<svg onload');
        expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    });

    it('links socials with rel="nofollow ugc noopener" and shows Discord as text', () => {
        const wrapper = mount(Show, { props: { profile: profile() } });
        const link = wrapper.get('a[href="https://www.youtube.com/@clashchief"]');

        expect(link.attributes('rel')).toBe('nofollow ugc noopener');
        expect(wrapper.find('a[href*="discord"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Discord: clashchief');
    });

    it('offers the owner the edit links and setup prompts, and others muted empty text', () => {
        const own = mount(Show, { props: { profile: profile({ isOwn: true }), ownAccounts: [] } });
        expect(own.text()).toContain('Edit profile');
        expect(own.text()).toContain('No accounts yet');
        expect(own.find('a[href="/accounts/attach"]').text()).toBe('Attach an account');

        const other = mount(Show, { props: { profile: profile() } });
        expect(other.text()).not.toContain('Edit profile');
        expect(other.text()).toContain('No public accounts.');
    });

    it("lists the owner's accounts with a verify link on unverified ones", () => {
        const account = (overrides: Partial<App.Domain.PlayerAccounts.Data.OwnCocAccountData>): App.Domain.PlayerAccounts.Data.OwnCocAccountData => ({
            ulid: '01J00000000000000000000001',
            tag: '#2PQ8GRJC',
            name: 'Main',
            status: 'verified',
            statusLabel: 'Verified',
            townHallLevel: 16,
            featured: true,
            ...overrides,
        });
        const wrapper = mount(Show, {
            props: {
                profile: profile({ isOwn: true }),
                ownAccounts: [account({}), account({ ulid: '01J00000000000000000000002', tag: '#GRJ0P8UV', name: 'Alt', status: 'unverified', statusLabel: 'Unverified', featured: false })],
            },
        });

        expect(wrapper.text()).not.toContain('No accounts yet');
        expect(wrapper.findAll('li').filter((item) => item.text().includes('#2PQ8GRJC'))[0]!.text()).not.toContain('Verify');
        const verify = wrapper.find('a[href="/accounts/01J00000000000000000000002/verify"]');
        expect(verify.text()).toBe('Verify');
        expect(verify.attributes('aria-label')).toBe('Verify Alt');
        expect(wrapper.find('a[href="/accounts/attach"]').text()).toBe('Attach another account');
    });

    it('falls back to the username and leaves out the about section when it is empty', () => {
        const wrapper = mount(Show, { props: { profile: profile({ displayName: null, bio: null, socials: [] }) } });

        expect(wrapper.get('h1').text()).toBe('chief');
        expect(wrapper.text()).not.toContain('About');
    });

    it('shows the skeleton while another profile loads', async () => {
        const wrapper = mount(Show, { props: { profile: profile() } });

        listeners.start!.forEach((l) => l({ detail: { visit: { method: 'get', prefetch: false, only: [], url: new URL('https://x.test/u/other') } } }));
        await nextTick();
        expect(wrapper.text()).toContain('Loading profile');

        listeners.finish!.forEach((l) => l({ detail: { visit: {} } }));
        await nextTick();
        expect(wrapper.text()).not.toContain('Loading profile');

        listeners.start!.forEach((l) => l({ detail: { visit: { method: 'get', prefetch: false, only: [], url: new URL('https://x.test/settings/privacy') } } }));
        await nextTick();
        expect(wrapper.text()).not.toContain('Loading profile');
    });
});
