import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const get = vi.fn();
const reload = vi.fn();
let pageUrl = '/search?q=TH17+war+ring';

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: pageUrl, props: { auth: { user: null, can: {} }, errors: {} } }),
    router: { get: (...args: unknown[]) => get(...args), reload: (...args: unknown[]) => reload(...args), on: () => () => undefined },
}));

import Index from '@/Pages/Search/Index.vue';

type Props = InstanceType<typeof Index>['$props'];

const filters: App.Domain.Bases.Data.FeedFiltersData = { thMin: 17, thMax: 17, category: 'war', tag: null, minLikes: null, hasVideo: false, sort: 'relevance' };

const props = (overrides: Partial<Props> = {}): Props => ({
    q: 'TH17 war ring',
    type: 'all',
    filters,
    parsed: [
        { key: 'th', value: '17', label: 'Town Hall 17', match: 'TH17' },
        { key: 'category', value: 'war', label: 'War', match: 'war' },
    ],
    tag: null,
    searched: ['bases', 'players', 'accounts'],
    more: { bases: false, players: true, accounts: false },
    options: { thMin: 15, thMax: 17, categories: [{ value: 'war', label: 'War' }], sorts: [{ value: 'relevance', label: 'Best match' }], suggestedTags: [] },
    facets: null,
    bases: [],
    players: [{ username: 'ringer', displayName: 'Ring Master', avatarUrl: null, bio: 'Builds rings.' }],
    accounts: [
        {
            ulid: 'acc',
            tag: '#2PPQ',
            name: 'Ring Chief',
            status: 'verified',
            statusLabel: 'Verified',
            townHallLevel: 16,
            townHall: null,
            builderHallLevel: null,
            xpLevel: 230,
            trophies: 5100,
            bestTrophies: 5200,
            warStars: 900,
            leagueName: 'Legend League',
            league: null,
            clan: null,
            clanHidden: true,
            featured: false,
            stale: false,
            syncedAt: null,
            syncedAgeSeconds: 60,
        },
    ],
    nextCursor: null,
    ...overrides,
});

beforeEach(() => {
    get.mockClear();
    reload.mockClear();
    pageUrl = '/search?q=TH17+war+ring';
});

describe('Search/Index', () => {
    it('shows the parsed filters as chips that remove their words', async () => {
        const wrapper = mount(Index, { props: props() });

        expect(wrapper.text()).toContain('Town Hall 17');
        await wrapper.get('button[aria-label="Remove Town Hall 17"]').trigger('click');

        expect(get).toHaveBeenCalledWith('/search', { q: 'war ring' }, { preserveScroll: false });
    });

    it('groups players and accounts, with the tag beside every account name and See all when there are more', async () => {
        const wrapper = mount(Index, { props: props() });

        expect(wrapper.text()).toContain('Ring Master');
        expect(wrapper.text()).toContain('#2PPQ');
        await wrapper.findAll('button').find((b) => b.text() === 'See all players')!.trigger('click');

        expect(get).toHaveBeenCalledWith('/search', { q: 'TH17 war ring', type: 'players' }, { preserveScroll: false });
        expect(wrapper.findAll('button').some((b) => b.text() === 'See all accounts')).toBe(false);
    });

    it('submits new text on the current tab', async () => {
        const wrapper = mount(Index, { props: props({ type: 'accounts' }) });

        await wrapper.get('input[type="search"]').setValue('  echo  ');
        await wrapper.get('form[role="search"]').trigger('submit');

        expect(get).toHaveBeenCalledWith('/search', { q: 'echo', type: 'accounts' }, { preserveScroll: false });
    });

    it('loads more of one kind with the query the page was loaded with', async () => {
        pageUrl = '/search?q=ring&type=players';
        const wrapper = mount(Index, { props: props({ q: 'ring', type: 'players', parsed: [], nextCursor: 'next.sig' }) });

        await wrapper.findAll('button').find((b) => b.text() === 'Load more')!.trigger('click');

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ only: ['players', 'nextCursor'], data: { q: 'ring', type: 'players', cursor: 'next.sig' }, preserveUrl: true }),
        );
    });

    it('explains a tag nobody may find, and an empty search', () => {
        expect(mount(Index, { props: props({ tag: '#2PPQ', searched: [] }) }).text()).toContain('No player with #2PPQ on Clash Commons yet');
        expect(mount(Index, { props: props({ q: '', parsed: [], searched: [], players: [], accounts: [] }) }).text()).toContain('Find bases, players and accounts');
    });

    it('asks for a name when only filters were typed on the players tab', () => {
        const wrapper = mount(Index, { props: props({ type: 'players', searched: ['bases'], players: [] }) });

        expect(wrapper.text()).toContain('Add a name to search players');
    });
});
