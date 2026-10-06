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
type Accounts = App.Domain.PlayerAccounts.Data.ProfileAccountsData;
type Card = App.Domain.PlayerAccounts.Data.PlayerCardData;

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

const card = (overrides: Partial<Card> = {}): Card => ({
    ulid: '01J00000000000000000000001',
    tag: '#2PQ8GRJC',
    name: 'Main',
    status: 'verified',
    statusLabel: 'Verified',
    townHallLevel: 16,
    townHall: null,
    builderHallLevel: null,
    xpLevel: 231,
    trophies: 5124,
    bestTrophies: 5524,
    warStars: 1480,
    leagueName: null,
    league: null,
    clan: null,
    clanHidden: false,
    featured: false,
    stale: false,
    syncedAt: '2026-10-06T11:48:00+00:00',
    syncedAgeSeconds: 720,
    ...overrides,
});

const none: Accounts = { cards: [], featured: null, warStars: null, verified: false };

const ssr = (props: Profile, accounts: Accounts = none) => renderToString(createSSRApp({ render: () => h(Show, { profile: props, accounts }) }));

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
        const wrapper = mount(Show, { props: { profile: profile(), accounts: none } });
        const link = wrapper.get('a[href="https://www.youtube.com/@clashchief"]');

        expect(link.attributes('rel')).toBe('nofollow ugc noopener');
        expect(wrapper.find('a[href*="discord"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Discord: clashchief');
    });

    it('offers the owner the edit links and setup prompts, and others muted empty text', () => {
        const own = mount(Show, { props: { profile: profile({ isOwn: true }), accounts: none } });
        expect(own.text()).toContain('Edit profile');
        expect(own.text()).toContain('No accounts yet');
        expect(own.find('a[href="/accounts/attach"]').text()).toBe('Attach an account');

        const other = mount(Show, { props: { profile: profile(), accounts: none } });
        expect(other.text()).not.toContain('Edit profile');
        expect(other.text()).toContain('No public accounts.');
    });

    it("shows the owner's cards with a verify link under unverified ones", () => {
        const wrapper = mount(Show, {
            props: {
                profile: profile({ isOwn: true }),
                accounts: {
                    cards: [card({ featured: true }), card({ ulid: '01J00000000000000000000002', tag: '#GRJ0P8UV', name: 'Alt', status: 'unverified', statusLabel: 'Unverified' })],
                    featured: card({ featured: true }),
                    warStars: 1480,
                    verified: true,
                },
            },
        });

        expect(wrapper.text()).not.toContain('No accounts yet');
        expect(wrapper.findAll('a[href$="/verify"]')).toHaveLength(1);
        const verify = wrapper.get('a[href="/accounts/01J00000000000000000000002/verify"]');
        expect(verify.text()).toBe('Verify');
        expect(verify.attributes('aria-label')).toBe('Verify Alt');
        expect(wrapper.find('a[href="/accounts/attach"]').text()).toBe('Attach another account');
    });

    it('shows the badge, the featured account panel and war stars to others when accounts are visible', () => {
        const featured = card({ featured: true });
        const wrapper = mount(Show, { props: { profile: profile(), accounts: { cards: [featured], featured, warStars: 1480, verified: true } } });

        expect(wrapper.get('h1').find('[role="img"]').exists()).toBe(false);
        expect(wrapper.find('[aria-label^="Verified player"]').exists()).toBe(true);
        const hero = wrapper.get('section[aria-label="Featured account"]');
        expect(hero.get('a').text()).toBe('Main');
        expect(hero.get('a').attributes('href')).toBe('/accounts/01J00000000000000000000001');
        expect(hero.text()).toContain('5,524');
        expect(hero.text()).not.toContain('Troops donated');
        expect(hero.text()).not.toContain('Troops received');
        expect(wrapper.text()).toContain('War stars');
        expect(wrapper.text()).not.toContain('No public accounts.');
        expect(wrapper.find('a[href$="/verify"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Attach another account');
    });

    it('leaves out the badge, the hero and war stars when no verified account is visible', () => {
        const wrapper = mount(Show, { props: { profile: profile(), accounts: none } });

        expect(wrapper.find('[aria-label^="Verified player"]').exists()).toBe(false);
        expect(wrapper.find('section[aria-label="Featured account"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('War stars');
        expect(wrapper.findAll('dl > *')).toHaveLength(3);
    });

    it('lets the bio fill the content width and wrap long words', () => {
        const bio = mount(Show, { props: { profile: profile(), accounts: none } }).get('section[aria-labelledby="profile-about"] p');

        expect(bio.classes()).not.toContain('max-w-prose');
        expect(bio.classes()).toContain('break-words');
    });

    it('falls back to the username and leaves out the about section when it is empty', () => {
        const wrapper = mount(Show, { props: { profile: profile({ displayName: null, bio: null, socials: [] }), accounts: none } });

        expect(wrapper.get('h1').text()).toBe('chief');
        expect(wrapper.text()).not.toContain('About');
    });

    it('shows the skeleton while another profile loads', async () => {
        const wrapper = mount(Show, { props: { profile: profile(), accounts: none } });

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
