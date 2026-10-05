import Index from '@/Pages/Admin/Disputes/Index.vue';
import Show from '@/Pages/Admin/Disputes/Show.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h, reactive } from 'vue';

const calls = vi.hoisted(() => [] as { method: string; url: string; data: Record<string, unknown> }[]);
const visits = vi.hoisted(() => [] as { url: string; data: Record<string, unknown> }[]);

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        setup:
            (props: { href: string }, { slots }: { slots: { default?: () => unknown } }) =>
            () =>
                h('a', { href: props.href }, slots.default?.() as never),
    },
    Deferred: {
        props: ['data'],
        setup:
            (_: unknown, { slots }: { slots: { default?: () => unknown } }) =>
            () =>
                slots.default?.(),
    },
    usePage: () => ({ props: {}, url: '/admin/disputes' }),
    router: { get: (url: string, data: Record<string, unknown>) => visits.push({ url, data }), reload: vi.fn(), on: () => () => {} },
    useForm: (initial: Record<string, unknown>) => {
        const form: Record<string, unknown> = reactive({ ...initial, errors: {}, processing: false, reset: () => Object.assign(form, initial) });
        form.post = (url: string, options: { onSuccess?: () => void }) => {
            calls.push({ method: 'post', url, data: Object.fromEntries(Object.keys(initial).map((key) => [key, form[key]])) });
            options.onSuccess?.();
        };
        return form;
    },
}));

afterEach(() => {
    calls.length = 0;
    visits.length = 0;
    document.body.innerHTML = '';
});

type Review = App.Domain.PlayerAccounts.Data.DisputeReviewData;
type Option = App.Domain.PlayerAccounts.Data.DisputeDecisionOptionData;

const party = (username: string): App.Domain.PlayerAccounts.Data.DisputePartyData => ({
    ulid: `01J00000000000000000000${username.slice(0, 3).toUpperCase()}`,
    username,
    roleLabel: 'User',
    statusLabel: 'Active',
    statusTone: 'success',
    joinedAt: '2026-01-01T00:00:00+00:00',
    verifiedAccounts: 1,
    deleted: false,
    priorDisputes: [],
});

const option = (value: Option['value'], label: string, unavailableReason: string | null = null): Option => ({
    value,
    label,
    available: unavailableReason === null,
    unavailableReason,
});

const review = (overrides: Partial<Review> = {}): Review => ({
    ulid: '01J0000000000000000000DISP',
    tag: '#2PQ8GRJC',
    status: 'awaiting_admin',
    statusLabel: 'Waiting for an admin',
    active: true,
    openedAt: '2026-10-01T00:00:00+00:00',
    waitingSince: '2026-10-02T00:00:00+00:00',
    escalatedAt: null,
    decidedAt: null,
    decidedBy: null,
    assignedTo: null,
    decisionNote: null,
    accountName: 'Fixture Chief',
    accountStatus: 'disputed',
    accountTownHall: 16,
    claimant: party('claimant'),
    holder: party('holder'),
    evidence: [
        { party: 'claimant', note: 'I lost the phone.', images: [], at: '2026-10-01T00:00:00+00:00', opening: true },
        {
            party: 'holder',
            note: 'It is mine.',
            images: [{ ulid: 'IMG', url: null, thumbUrl: null }],
            at: '2026-10-02T00:00:00+00:00',
            opening: false,
        },
    ],
    claims: [],
    snapshots: [],
    decisions: [
        option('transfer', 'Transfer to the claimant'),
        option('deny', 'Deny the claim', 'A banned holder cannot keep the account.'),
        option('suspend', 'Suspend the account'),
        option('ask_claimant', 'Ask the claimant for more'),
        option('ask_holder', 'Ask the holder for more'),
    ],
    blockedReason: null,
    noteMax: 1000,
    ...overrides,
});

const renderShow = (dispute: Review) =>
    mount(Show, {
        props: { dispute, claimantSanctions: [], holderSanctions: [] },
        attachTo: document.body,
        global: { stubs: { AdminLayout: true } },
    });

async function choose(wrapper: ReturnType<typeof renderShow>, value: string, note = 'Weighed the receipts.') {
    await wrapper.find(`input[type="radio"][value="${value}"]`).setValue(true);
    await wrapper.find('textarea').setValue(note);
    await wrapper.find('form').trigger('submit');
    await flushPromises();
}

describe('Admin/Disputes/Show', () => {
    it('shows both sides, the evidence and an image that is not ready', () => {
        const text = renderShow(review()).text();

        expect(text).toContain('claimant');
        expect(text).toContain('holder');
        expect(text).toContain('Opening statement');
        expect(text).toContain('I lost the phone.');
        expect(text).toContain('Image not available');
    });

    it('offers only the available decisions and says why the others wait', () => {
        const wrapper = renderShow(review());

        expect(wrapper.findAll('input[type="radio"]').map((input) => (input.element as HTMLInputElement).value)).toEqual([
            'transfer',
            'suspend',
            'ask_claimant',
            'ask_holder',
        ]);
        expect(wrapper.text()).toContain('Deny the claim: A banned holder cannot keep the account.');
    });

    it('confirms a transfer, naming who gets the account, before it is sent', async () => {
        const wrapper = renderShow(review());
        await choose(wrapper, 'transfer');

        expect(calls).toEqual([]);
        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog.textContent).toContain('#2PQ8GRJC becomes verified for claimant. holder keeps an unverified row.');

        const confirm = Array.from(dialog.querySelectorAll('button')).find((button) =>
            button.textContent?.includes('Record the decision'),
        ) as HTMLButtonElement;
        confirm.click();
        await flushPromises();
        expect(calls).toEqual([
            {
                method: 'post',
                url: '/admin/disputes/01J0000000000000000000DISP/decision',
                data: { decision: 'transfer', note: 'Weighed the receipts.' },
            },
        ]);
    });

    it('sends a question without a confirmation', async () => {
        const wrapper = renderShow(review());
        await choose(wrapper, 'ask_holder', 'Send a screenshot of your settings.');

        expect(document.body.querySelector('[role="dialog"]')).toBeNull();
        expect(calls).toEqual([
            {
                method: 'post',
                url: '/admin/disputes/01J0000000000000000000DISP/decision',
                data: { decision: 'ask_holder', note: 'Send a screenshot of your settings.' },
            },
        ]);
    });

    it('replaces the form with the reason when this admin cannot decide', () => {
        const wrapper = renderShow(review({ blockedReason: 'Needs a super admin: an admin is part of this dispute.' }));

        expect(wrapper.text()).toContain('Needs a super admin');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});

describe('Admin/Disputes/Index', () => {
    const page = {
        view: 'active',
        mine: false,
        views: [
            { value: 'active', label: 'All running' },
            { value: 'closed', label: 'Closed' },
        ],
    };
    const row: App.Domain.PlayerAccounts.Data.DisputeQueueRowData = {
        ulid: '01J0000000000000000000DISP',
        tag: '#2PQ8GRJC',
        claimant: 'claimant',
        holder: 'holder',
        status: 'awaiting_admin',
        statusLabel: 'Waiting for an admin',
        waitingSince: '2026-10-02T00:00:00+00:00',
        openedAt: '2026-10-01T00:00:00+00:00',
        assignedTo: null,
        needsSuperAdmin: true,
    };

    it('lists disputes linking to their review, with the rank note', () => {
        const wrapper = mount(Index, {
            props: { ...page, disputes: { entries: [row], nextCursor: null, previousCursor: null } },
            global: { stubs: { AdminLayout: true } },
        });

        expect(wrapper.find('a[href="/admin/disputes/01J0000000000000000000DISP"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Needs a super admin');
    });

    it('says when nothing waits', () => {
        const wrapper = mount(Index, {
            props: { ...page, disputes: { entries: [], nextCursor: null, previousCursor: null } },
            global: { stubs: { AdminLayout: true } },
        });

        expect(wrapper.text()).toContain('No disputes waiting.');
    });

    it('applies the filters as query parameters', async () => {
        const wrapper = mount(Index, {
            props: { ...page, disputes: { entries: [], nextCursor: null, previousCursor: null } },
            global: { stubs: { AdminLayout: true } },
        });
        await wrapper.find('select').setValue('closed');
        await wrapper.find('input[type="checkbox"]').setValue(true);
        await wrapper.find('form').trigger('submit');

        expect(visits).toEqual([{ url: '/admin/disputes', data: { view: 'closed', mine: '1' } }]);
    });
});
