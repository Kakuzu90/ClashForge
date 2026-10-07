import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const get = vi.fn();
const reload = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/bases', props: { auth: { user: null, can: {} } } }),
    router: { get: (...args: unknown[]) => get(...args), reload: (...args: unknown[]) => reload(...args), on: () => () => undefined },
}));

import Index from '@/Pages/Bases/Index.vue';

const filters: App.Domain.Bases.Data.FeedFiltersData = { thMin: null, thMax: null, category: null, tag: null, minLikes: null, hasVideo: false, sort: 'trending' };

type Card = App.Domain.Bases.Data.BaseCardData;

type Props = InstanceType<typeof Index>['$props'];

const props = (overrides: Partial<Props> = {}): Props => ({
    filters,
    options: {
        thMin: 15,
        thMax: 17,
        categories: [{ value: 'war', label: 'War' }],
        sorts: [
            { value: 'trending', label: 'Trending' },
            { value: 'liked', label: 'Most liked' },
        ],
        suggestedTags: ['ring-base', 'box'],
    },
    cards: [
        {
            ulid: 'a',
            slug: 'a-ring',
            title: 'Ring',
            thLevel: 16,
            category: 'war',
            hasVideo: true,
            cover: null,
            likes: 1,
            copies: 0,
            views: 3,
            author: { username: 'chief', displayName: null, avatarUrl: null },
            credit: null,
        } satisfies Card,
    ],
    nextCursor: 'next.sig',
    ...overrides,
});

beforeEach(() => {
    get.mockClear();
    reload.mockClear();
});

describe('Bases/Index', () => {
    it('loads the next page as a partial reload that keeps the URL', async () => {
        const wrapper = mount(Index, { props: props() });

        await wrapper.findAll('button').find((b) => b.text() === 'Load more')!.trigger('click');

        expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['cards', 'nextCursor'], data: { cursor: 'next.sig' }, preserveUrl: true }));
    });

    it('applies each desktop filter change at once, with the query names the server reads', async () => {
        const wrapper = mount(Index, { props: props() });
        const form = wrapper.get('aside form');

        expect(form.findAll('button').some((b) => b.text() === 'Apply filters')).toBe(false);

        await form.findAll('button').find((b) => b.text() === 'ring-base')!.trigger('click');
        await nextTick();
        expect(get).toHaveBeenLastCalledWith('/bases', { tag: 'ring-base' }, { preserveScroll: false });

        const likes = form.get('input[type="number"]');
        await likes.setValue('25');
        await likes.trigger('change');
        await nextTick();
        expect(get).toHaveBeenLastCalledWith('/bases', { tag: 'ring-base', min_likes: '25' }, { preserveScroll: false });
    });

    it('applies the phone sheet\'s filters together with its button', async () => {
        const wrapper = mount(Index, { props: props(), attachTo: document.body });
        await wrapper.findAll('button').find((b) => b.text() === 'Filters')!.trigger('click');
        const sheet = document.querySelector('[role="dialog"] form') as HTMLFormElement;

        (sheet.querySelector('button[aria-pressed]') as HTMLButtonElement).click();
        await nextTick();
        expect(get).not.toHaveBeenCalled();

        sheet.dispatchEvent(new Event('submit'));
        await nextTick();
        expect(get).toHaveBeenCalledWith('/bases', { tag: 'ring-base' }, { preserveScroll: false });
        wrapper.unmount();
    });

    it('picks a Town Hall from the chip row and resets everything', async () => {
        const wrapper = mount(Index, { props: props({ filters: { ...filters, tag: 'box', hasVideo: true } }) });

        await wrapper.findAll('button').find((b) => b.text() === 'TH 16')!.trigger('click');
        expect(get).toHaveBeenLastCalledWith('/bases', { th: '16', tag: 'box', video: '1' }, { preserveScroll: false });

        await wrapper.get('aside').findAll('button').find((b) => b.text() === 'Reset')!.trigger('click');
        expect(get).toHaveBeenLastCalledWith('/bases', {}, { preserveScroll: false });
    });

    it('counts the active filters on the phone button and offers a reset when nothing matches', () => {
        const wrapper = mount(Index, { props: props({ cards: [], nextCursor: null, filters: { ...filters, thMin: 16, thMax: 16, hasVideo: true } }) });

        expect(wrapper.text()).toContain('Filters (2)');
        expect(wrapper.text()).toContain('No bases match these filters');
        expect(wrapper.findAll('button').some((b) => b.text() === 'Reset filters')).toBe(true);
    });
});
