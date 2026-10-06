import Show from '@/Pages/Accounts/Show.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h, reactive } from 'vue';

const page = vi.hoisted(() => ({ props: { auth: { user: null, can: {} }, cocApi: null as unknown }, url: '/accounts/x' }));
const sent = vi.hoisted(() => ({ visits: [] as { method: string; url: string; data: Record<string, unknown> }[], errors: {} as Record<string, string>, processing: false }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], setup: (props: { href: string }, { slots }: { slots: { default?: () => unknown } }) => () => h('a', { href: props.href }, slots.default?.() as never) },
    Deferred: { props: ['data'], setup: (_: unknown, { slots }: { slots: { default?: () => unknown } }) => () => slots.default?.() },
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) => {
        const form = reactive({
            ...initial,
            processing: sent.processing,
            errors: {} as Record<string, string>,
            reset: (...fields: string[]) => fields.forEach((field) => ((form as Record<string, unknown>)[field] = initial[field])),
        });
        const visit = (method: string) => (url: string, options: { onFinish?: () => void; onError?: () => void } = {}) => {
            sent.visits.push({ method, url, data: Object.fromEntries(Object.keys(initial).map((key) => [key, (form as Record<string, unknown>)[key]])) });
            form.errors = { ...sent.errors };
            if (Object.keys(sent.errors).length > 0) {
                options.onError?.();
            }
            options.onFinish?.();
        };
        return Object.assign(form, { put: visit('put'), post: visit('post'), delete: visit('delete') });
    },
}));

afterEach(() => {
    vi.useRealTimers();
    sent.visits.length = 0;
    sent.errors = {};
    sent.processing = false;
    document.body.innerHTML = '';
});

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
        bestTrophies: 5524,
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
    stats: [
        { key: 'trophies', label: 'Trophies', value: 5124, delta: 24 },
        { key: 'donations', label: 'Troops donated', value: 104, delta: null },
        { key: 'builder_trophies', label: 'Builder Base trophies', value: 3120, delta: null },
        { key: 'best_builder_trophies', label: 'Best Builder Base trophies', value: 4600, delta: null },
    ],
    builderLeagueName: 'Ruby League III',
    builderLeague: { kind: 'league', url: null, alt: 'Ruby League III', short: 'RL', width: null, height: null },
    builderHall: { kind: 'town_hall', url: null, alt: 'Builder Hall 10', short: '10', width: null, height: null },
    deltaDays: 7,
    notFound: false,
    isOwn: false,
    canVerify: false,
    canDetach: false,
    canFeature: false,
    canRefresh: false,
    refreshWaitSeconds: 0,
    indexable: true,
    disputeUlid: null,
    ...rest,
});

type Group = App.Domain.PlayerAccounts.Data.ProgressionGroupData;

const group = (key: string, label: string, village: Group['village'], name: string): Group => ({
    key,
    label,
    village,
    units: [{ name, asset: { kind: 'unit', url: null, alt: name, short: 'U', width: null, height: null }, level: 5, maxLevel: 10, maxed: false, locked: false, equipment: [] }],
});

const render = (account: Detail, cocApi: unknown = null, progression: Group[] = []) => {
    page.props.cocApi = cocApi;

    return mount(Show, { props: { account, progression }, attachTo: document.body, global: { stubs: { AppLayout: true } } });
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

    it('opens on the Home Village tab, with the profile and the home grids only', () => {
        const wrapper = render(detail(), null, [
            group('heroes', 'Heroes', 'home', 'Archer Queen'),
            group('troops', 'Troops', 'home', 'Barbarian'),
            group('builder_troops', 'Troops', 'builderBase', 'Raged Barbarian'),
        ]);
        const [home, builder] = wrapper.findAll('[role="tabpanel"]');

        expect(wrapper.findAll('[role="tab"]').map((tab) => [tab.text(), tab.attributes('aria-selected')])).toEqual([
            ['Home Village', 'true'],
            ['Builder Base', 'false'],
        ]);
        expect(wrapper.text()).not.toContain('Clan Capital');
        expect(home.text()).toContain('Troops donated');
        expect(home.text()).toContain('Archer Queen, level 5 of 10');
        expect(home.text()).not.toContain('Raged Barbarian');
        expect(builder.text()).toContain('Builder Hall 10');
        expect(builder.text()).toContain('Raged Barbarian, level 5 of 10');
    });

    it('switches to the Builder Base tab', async () => {
        const wrapper = render(detail());
        await wrapper.findAll('[role="tab"]')[1].trigger('click');

        expect(wrapper.findAll('[role="tab"]')[1].attributes('aria-selected')).toBe('true');
        expect((wrapper.findAll('[role="tabpanel"]')[1].element as HTMLElement).style.display).toBe('');
        expect((wrapper.findAll('[role="tabpanel"]')[0].element as HTMLElement).style.display).toBe('none');
    });

    it('keeps the same profile on the Builder Base tab, with its own ranked data', () => {
        const [home, builder] = render(detail({ leagueName: 'Legend League', clan: null })).findAll('[role="tabpanel"]');

        for (const panel of [home, builder]) {
            expect(panel.text()).toContain('Fixture Chief');
            expect(panel.text()).toContain('War stars won');
            expect(panel.text()).toContain('Troops donated');
        }
        expect(home.text()).toContain('Legend League');
        expect(home.text()).toContain('5,124');
        expect(builder.text()).toContain('Ruby League III');
        expect(builder.text()).toContain('3,120');
        expect(builder.text()).toContain('4,600');
        expect(builder.text()).not.toContain('Legend League');
    });

    it('keeps one page heading whichever tab is open', () => {
        expect(render(detail()).findAll('h1').map((h) => h.text())).toEqual(['Fixture Chief #2PQ8GRJC']);
    });
    it('shows the owner actions only from the server flags (P2-14)', () => {
        expect(render(detail()).text()).not.toContain('Remove account');
        expect(render(detail()).text()).not.toContain('Make featured');

        const text = render(detail({}, { isOwn: true, canDetach: true, canFeature: true })).text();
        expect(text).toContain('Make featured');
        expect(text).toContain('Remove account');
    });

    it('makes the account featured', async () => {
        const wrapper = render(detail({}, { isOwn: true, canFeature: true }));
        await wrapper.findAll('button').find((b) => b.text() === 'Make featured')!.trigger('click');

        expect(sent.visits).toEqual([{ method: 'put', url: '/accounts/01J0000000000000000000CARD/featured', data: {} }]);
    });

    it('asks for the password before removing, and says what happens to the tag', async () => {
        const wrapper = render(detail({}, { isOwn: true, canDetach: true }));
        expect(document.body.querySelector('[role="dialog"]')).toBeNull();

        await wrapper.findAll('button').find((b) => b.text() === 'Remove account')!.trigger('click');
        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog.textContent).toContain('Remove this account?');
        expect(dialog.textContent).toContain('#2PQ8GRJC will no longer be on your Clash Commons account and loses its verified badge.');
        expect(dialog.textContent).toContain('Anyone with its in-game API token can verify it');

        const input = dialog.querySelector('input[type="password"]') as HTMLInputElement;
        input.value = 'secret-password';
        input.dispatchEvent(new Event('input'));
        await flushPromises();
        (dialog.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await flushPromises();

        expect(sent.visits).toEqual([{ method: 'delete', url: '/accounts/01J0000000000000000000CARD', data: { current_password: 'secret-password' } }]);
        expect(input.value).toBe('');
    });

    it('keeps the dialog open with the error on a wrong password', async () => {
        sent.errors = { current_password: 'That is not your current password.' };
        const wrapper = render(detail({ status: 'unverified', statusLabel: 'Unverified' }, { isOwn: true, canDetach: true }));
        await wrapper.findAll('button').find((b) => b.text() === 'Remove account')!.trigger('click');
        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog.textContent).not.toContain('verified badge');

        (dialog.querySelector('form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await flushPromises();

        expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain('That is not your current password.');
    });
    it('disables the dialog while removing and the featured button while it saves', async () => {
        sent.processing = true;
        const wrapper = render(detail({}, { isOwn: true, canDetach: true, canFeature: true }));
        expect(wrapper.findAll('button').find((b) => b.text().includes('Make featured'))!.attributes('disabled')).toBeDefined();

        await wrapper.findAll('button').find((b) => b.text() === 'Remove account')!.trigger('click');
        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect((dialog.querySelector('input[type="password"]') as HTMLInputElement).disabled).toBe(true);
        expect((dialog.querySelector('button[type="submit"]') as HTMLButtonElement).disabled).toBe(true);
    });

    it('shows the wait when password guesses are throttled', async () => {
        sent.errors = { current_password: 'Too many attempts. Try again in 60 seconds.' };
        const wrapper = render(detail({}, { isOwn: true, canDetach: true }));
        await wrapper.findAll('button').find((b) => b.text() === 'Remove account')!.trigger('click');
        (document.body.querySelector('[role="dialog"] form') as HTMLFormElement).dispatchEvent(new Event('submit'));
        await flushPromises();

        expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain('Too many attempts. Try again in 60 seconds.');
    });

    describe('manual refresh (P2-20)', () => {
        const refreshButton = (wrapper: ReturnType<typeof render>) => wrapper.findAll('button').find((b) => b.text().startsWith('Refresh'));

        it('shows the button to the owner only, from the server flag', () => {
            expect(refreshButton(render(detail()))).toBeUndefined();
            expect(refreshButton(render(detail({}, { isOwn: true, canRefresh: true })))).toBeDefined();
        });

        it('refreshes the account', async () => {
            const wrapper = render(detail({}, { isOwn: true, canRefresh: true }));
            await refreshButton(wrapper)!.trigger('click');

            expect(sent.visits).toEqual([{ method: 'post', url: '/accounts/01J0000000000000000000CARD/refresh', data: {} }]);
        });

        it('is busy while refreshing', () => {
            sent.processing = true;
            const button = refreshButton(render(detail({}, { isOwn: true, canRefresh: true })))!;

            expect(button.attributes('disabled')).toBeDefined();
            expect(button.attributes('aria-busy')).toBe('true');
        });

        it('counts the cooldown down and comes back on when it ends', async () => {
            vi.useFakeTimers();
            const wrapper = render(detail({}, { isOwn: true, canRefresh: true, refreshWaitSeconds: 61 }));
            expect(refreshButton(wrapper)!.attributes('disabled')).toBeDefined();
            expect(wrapper.text()).toContain('You can refresh again in 2 minutes.');
            expect(refreshButton(wrapper)!.attributes('aria-describedby')).toBe('refresh-note');

            vi.advanceTimersByTime(2000);
            await flushPromises();
            expect(wrapper.text()).toContain('You can refresh again in 1 minute.');

            vi.advanceTimersByTime(60000);
            await flushPromises();
            expect(refreshButton(wrapper)!.attributes('disabled')).toBeUndefined();
            expect(wrapper.text()).not.toContain('You can refresh again');
        });

        it('is paused while the game API is down', async () => {
            const wrapper = render(detail({}, { isOwn: true, canRefresh: true }), { reason: 'maintenance' });
            await refreshButton(wrapper)!.trigger('click');

            expect(refreshButton(wrapper)!.attributes('disabled')).toBeDefined();
            expect(wrapper.text()).toContain('Refresh is paused while the game API is unavailable.');
            expect(sent.visits).toEqual([]);
        });
    });
});
