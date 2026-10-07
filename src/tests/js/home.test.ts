import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

let user: { username: string } | null = null;
const get = vi.fn();
const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/', props: { auth: { user, can: {} } } }),
    router: { get: (...args: unknown[]) => get(...args), reload: (...args: unknown[]) => reload(...args), on: () => () => undefined },
}));

import Index from '@/Pages/Home/Index.vue';

type Card = App.Domain.Bases.Data.BaseCardData;

const card = (ulid: string): Card => ({
    ulid,
    slug: `${ulid}-ring`,
    title: `Base ${ulid}`,
    thLevel: 16,
    category: 'war',
    hasVideo: false,
    cover: null,
    likes: 3,
    copies: 1,
    views: 40,
    author: { username: 'chief', displayName: null, avatarUrl: null },
    credit: null,
});

const filters: App.Domain.Bases.Data.FeedFiltersData = { thMin: null, thMax: null, category: null, tag: null, minLikes: null, hasVideo: false, sort: 'trending' };

type Props = InstanceType<typeof Index>['$props'];

const props = (overrides: Partial<Props> = {}): Props => ({
    filters,
    thFromAccount: false,
    options: {
        thMin: 15,
        thMax: 17,
        categories: [{ value: 'war', label: 'War' }],
        sorts: [
            { value: 'trending', label: 'Trending' },
            { value: 'new', label: 'New' },
            { value: 'copied', label: 'Most copied' },
        ],
        suggestedTags: [],
    },
    cards: [card('a'), card('b')],
    nextCursor: null,
    ...overrides,
});

describe('Home/Index', () => {
    it('renders the product name as the only h1', () => {
        const wrapper = mount(Index, { props: props() });
        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.get('h1').text()).toBe('Clash Commons');
    });

    it('offers guests the way to create an account, and hides it once signed in', () => {
        user = null;
        expect(mount(Index, { props: props() }).get('a[href="/register"]').text()).toBe('Create your account');

        user = { username: 'chief' };
        expect(mount(Index, { props: props() }).find('a[href="/register"]').exists()).toBe(false);
    });

    it('shows the cards under the sort tabs, and the empty state with a reset when filtered', async () => {
        const wrapper = mount(Index, { props: props() });
        expect(wrapper.findAll('[role="tab"]').map((tab) => tab.text())).toEqual(['Trending', 'New', 'Most copied']);
        expect(wrapper.findAll('article')).toHaveLength(2);

        const empty = mount(Index, { props: props({ cards: [], filters: { ...filters, thMin: 16, thMax: 16 } }) });
        expect(empty.text()).toContain('No bases match these filters');
        await empty.findAll('button').find((b) => b.text() === 'Reset filters')!.trigger('click');
        expect(get).toHaveBeenLastCalledWith('/', { th: 'all' }, { preserveScroll: false });
    });

    it('shows the signed-in Town Hall default as a removable chip, removed as every Town Hall', async () => {
        const wrapper = mount(Index, { props: props({ thFromAccount: true, filters: { ...filters, thMin: 15, thMax: 17 } }) });

        expect(wrapper.text()).toContain('TH 15-17, your Town Hall');
        await wrapper.get('button[aria-label="Remove TH 15-17, your Town Hall"]').trigger('click');
        expect(get).toHaveBeenLastCalledWith('/', { th: 'all' }, { preserveScroll: false });
    });

    it('changes the sort through the tabs, keeping the Town Hall', async () => {
        const wrapper = mount(Index, { props: props({ filters: { ...filters, thMin: 16, thMax: 16 } }) });

        await wrapper.findAll('[role="tab"]')[2]!.trigger('click');
        expect(get).toHaveBeenLastCalledWith('/', { th: '16', sort: 'copied' }, { preserveScroll: false });
    });

    it('keeps the signed-in default on a sort change, and sends the shown Town Hall with "Load more"', async () => {
        const wrapper = mount(Index, { props: props({ thFromAccount: true, nextCursor: 'next.sig', filters: { ...filters, thMin: 15, thMax: 17 } }) });

        await wrapper.findAll('[role="tab"]')[1]!.trigger('click');
        expect(get).toHaveBeenLastCalledWith('/', { sort: 'new' }, { preserveScroll: false });

        await wrapper.findAll('button').find((b) => b.text() === 'Load more')!.trigger('click');
        expect(reload).toHaveBeenLastCalledWith(expect.objectContaining({ data: { th: '15-17', cursor: 'next.sig' } }));
    });
});
