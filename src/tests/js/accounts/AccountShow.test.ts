import Show from '@/Pages/Accounts/Show.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { h } from 'vue';

const page = vi.hoisted(() => ({ props: { auth: { user: null, can: {} }, cocApi: null as unknown }, url: '/accounts/x' }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], setup: (props: { href: string }, { slots }: { slots: { default?: () => unknown } }) => () => h('a', { href: props.href }, slots.default?.() as never) },
    Deferred: { props: ['data'], setup: (_: unknown, { slots }: { slots: { default?: () => unknown } }) => () => slots.default?.() },
    usePage: () => page,
}));

type Detail = App.Domain.PlayerAccounts.Data.AccountDetailData;

const asset = { kind: 'town_hall' as const, url: null, alt: 'Town Hall 16', short: '16', width: null, height: null };
const detail = (card: Partial<Detail['card']> = {}, rest: Partial<Detail> = {}): Detail => ({
    card: {
        ulid: '01J0000000000000000000CARD',
        tag: '#2PQ8GRJC',
        name: 'Fixture Chief',
        status: 'verified',
        statusLabel: 'Verified',
        townHallLevel: 16,
        townHall: asset,
        builderHallLevel: null,
        xpLevel: 231,
        trophies: 5124,
        warStars: 1480,
        leagueName: null,
        league: null,
        clan: null,
        clanHidden: false,
        featured: false,
        stale: false,
        syncedAt: '2026-10-03T11:00:00+00:00',
        syncedAgeSeconds: 3600,
        ...card,
    },
    stats: [{ key: 'trophies', label: 'Trophies', value: 5124, delta: 24 }],
    deltaDays: 7,
    notFound: false,
    isOwn: false,
    canVerify: false,
    indexable: true,
    ...rest,
});

const render = (account: Detail, cocApi: unknown = null) => {
    page.props.cocApi = cocApi;

    return mount(Show, { props: { account, progression: [] }, global: { stubs: { AppLayout: true } } });
};

describe('Accounts/Show', () => {
    it('shows the data age while the game API is down', () => {
        const text = render(detail(), { state: 'open', reason: 'maintenance', openUntil: null }).text();

        expect(text).toContain('Game data is temporarily unavailable');
        expect(text).toContain('Showing data from 1 h ago.');
        expect(text).not.toContain('This data is out of date');
    });

    it('says stale data is out of date when the API is up', () => {
        const text = render(detail({ stale: true, syncedAgeSeconds: 864000 })).text();

        expect(text).toContain('This data is out of date');
        expect(text).toContain('It was last updated 10 d ago.');
    });

    it('lets a disputed owner verify from the review banner, and nobody else', () => {
        expect(render(detail({ status: 'disputed' }, { isOwn: true, canVerify: true })).find('a[href="/accounts/01J0000000000000000000CARD/verify"]').exists()).toBe(true);
        expect(render(detail({ status: 'disputed' })).find('a[href$="/verify"]').exists()).toBe(false);
    });

    it('explains a suspended row to its owner', () => {
        expect(render(detail({ status: 'suspended', statusLabel: 'Suspended' }, { isOwn: true })).text()).toContain('This account is suspended');
    });

    it('shows the empty state when there are no grids', () => {
        expect(render(detail()).text()).toContain('No unit data yet');
    });
});
