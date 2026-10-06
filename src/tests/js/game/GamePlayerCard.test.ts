import GamePlayerCard from '@/Components/game/GamePlayerCard.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

type Card = App.Domain.PlayerAccounts.Data.PlayerCardData;

const asset = (kind: App.Domain.GameAssets.Enums.GameAssetKind, alt: string): App.Domain.GameAssets.Data.GameAssetData => ({
    kind,
    url: 'https://cdn.test/a.png',
    alt,
    short: 'A',
    width: 64,
    height: 64,
});

const card = (overrides: Partial<Card> = {}): Card => ({
    ulid: '01J0000000000000000000CARD',
    tag: '#2PQ8GRJC',
    name: 'Fixture Chief',
    status: 'verified',
    statusLabel: 'Verified',
    townHallLevel: 16,
    townHall: asset('town_hall', 'Town Hall 16'),
    builderHallLevel: 10,
    xpLevel: 231,
    trophies: 5124,
    bestTrophies: 5524,
    warStars: 1480,
    leagueName: 'Legend League',
    league: asset('league', 'Legend League'),
    clan: { tag: '#2Q8URJ9L', name: 'Night Owls', level: 22, roleLabel: 'Co-leader', badge: asset('clan_badge', 'Night Owls clan badge') },
    clanHidden: false,
    featured: false,
    stale: false,
    syncedAt: '2026-10-03T11:48:00+00:00',
    syncedAgeSeconds: 720,
    ...overrides,
});

describe('GamePlayerCard', () => {
    it('renders a verified hero card with its heading, stats, clan and age', () => {
        const wrapper = mount(GamePlayerCard, { props: { card: card(), variant: 'hero' } });

        expect(wrapper.get('h1').text()).toBe('Fixture Chief');
        expect(wrapper.find('[aria-label^="Verified"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('5,124');
        expect(wrapper.text()).toContain('Night Owls');
        expect(wrapper.text()).toContain('Co-leader · Level 22');
        expect(wrapper.text()).toContain('Updated 12 min ago');
        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('shows the list card with the XP level, no Town Hall, stats or clan, and the actions slot', () => {
        const wrapper = mount(GamePlayerCard, {
            props: { card: card({ status: 'disputed', statusLabel: 'Under review' }) },
            slots: { actions: '<a href="/x">Act</a>' },
        });

        expect(wrapper.text()).toContain('XP level 231');
        expect(wrapper.find('[aria-label^="Town Hall"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('5,124');
        expect(wrapper.text()).not.toContain('Night Owls');
        expect(wrapper.get('h3').classes()).toContain('truncate');
        expect(wrapper.get('article').classes()).toContain('h-full');
        expect(wrapper.text()).toContain('Under review');
        expect(wrapper.find('p.absolute').exists()).toBe(false);
        expect(wrapper.get('a[href="/x"]').element.parentElement!.className).toContain('z-10');
    });

    it('marks a featured card with the star icon, not text, and keeps the league name on one line', () => {
        const wrapper = mount(GamePlayerCard, { props: { card: card({ featured: true, leagueName: 'Golem League 19' }) } });

        expect(wrapper.find('[role="img"][aria-label^="Featured"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Featured');
        const league = wrapper.findAll('span').filter((span) => span.text() === 'Golem League 19');
        expect(league.at(-1)!.classes()).toContain('whitespace-nowrap');
    });

    it('links the standard and compact cards to the account page', () => {
        expect(
            mount(GamePlayerCard, { props: { card: card(), variant: 'standard' } })
                .get('a')
                .attributes('href'),
        ).toBe('/accounts/01J0000000000000000000CARD');
        expect(
            mount(GamePlayerCard, { props: { card: card(), variant: 'compact' } })
                .get('a')
                .attributes('href'),
        ).toBe('/accounts/01J0000000000000000000CARD');
    });

    it('labels unverified and disputed cards and says how old stale data is', () => {
        expect(mount(GamePlayerCard, { props: { card: card({ status: 'unverified', statusLabel: 'Unverified' }) } }).text()).toContain('Unverified');
        expect(mount(GamePlayerCard, { props: { card: card({ status: 'disputed', statusLabel: 'Under review' }) } }).text()).toContain(
            'Under review',
        );
        expect(mount(GamePlayerCard, { props: { card: card({ stale: true, syncedAgeSeconds: 259200 }) } }).text()).toContain('Data from 3 d ago');
        expect(mount(GamePlayerCard, { props: { card: card({ status: 'suspended', statusLabel: 'Suspended' }) } }).text()).toContain('Suspended');
    });

    it('never filters or fades a game asset, whatever the state', () => {
        const wrapper = mount(GamePlayerCard, { props: { card: card({ status: 'unverified', stale: true }), variant: 'hero' } });

        for (const img of wrapper.findAll('img')) {
            let el: Element | null = img.element;
            while (el && el !== wrapper.element.parentElement) {
                expect(Array.from(el.classList).some((c) => /^(grayscale|opacity|filter|blur|saturate)/.test(c))).toBe(false);
                el = el.parentElement;
            }
        }
    });

    it('reads "No clan" outside a clan, "Clan not shared" when the owner hides it, and "Not available" for missing stats', () => {
        const hero = (overrides: Partial<Card>) => mount(GamePlayerCard, { props: { card: card(overrides), variant: 'hero' } }).text();
        expect(hero({ clan: null })).toContain('No clan');
        expect(hero({ clan: null, clanHidden: true })).toContain('Clan not shared');
        expect(hero({ trophies: null })).toContain('Not available');
    });

    it('shows a skeleton while loading', () => {
        expect(mount(GamePlayerCard, { props: { card: null } }).text()).toContain('Loading account');
    });
});
