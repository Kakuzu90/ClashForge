import Index from '@/Pages/Admin/Disputes/Index.vue';
import Show from '@/Pages/Admin/Disputes/Show.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h, reactive } from 'vue';

const calls = vi.hoisted(() => [] as { method: string; url: string; data: Record<string, unknown> }[]);
const visits = vi.hoisted(() => [] as { url: string; data: Record<string, unknown> }[]);
// Errors the next form post answers with, as a refused request would.
const nextErrors = vi.hoisted(() => ({ value: null as Record<string, string> | null }));

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
    router: {
        get: (url: string, data: Record<string, unknown>) => visits.push({ url, data }),
        delete: (url: string) => calls.push({ method: 'delete', url, data: {} }),
        reload: vi.fn(),
        on: () => () => {},
    },
    useForm: (initial: Record<string, unknown>) => {
        const form: Record<string, unknown> = reactive({ ...initial, errors: {}, processing: false, reset: () => Object.assign(form, initial) });
        form.post = (url: string, options: { onSuccess?: () => void; onError?: () => void; onFinish?: () => void }) => {
            calls.push({ method: 'post', url, data: Object.fromEntries(Object.keys(initial).map((key) => [key, form[key]])) });
            if (nextErrors.value) {
                form.errors = nextErrors.value;
                nextErrors.value = null;
                options.onError?.();
            } else {
                options.onSuccess?.();
            }
            options.onFinish?.();
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
        { party: 'claimant', note: 'I lost the phone.', images: [], at: '2026-10-01T00:00:00+00:00', opening: true, removed: 0, removable: false },
        {
            party: 'holder',
            note: 'It is mine.',
            images: [{ ulid: 'IMG', url: null, thumbUrl: null }],
            at: '2026-10-02T00:00:00+00:00',
            opening: false,
            removed: 0,
            removable: false,
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
    canReleaseTag: false,
    releaseBlockedReason: null,
    ...overrides,
});

const renderShow = (dispute: Review) =>
    mount(Show, {
        props: { dispute, claimantSanctions: [], holderSanctions: [], auditTrail: [], moreAuditEntries: false },
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
        const wrapper = renderShow(review({ blockedReason: 'An admin is a party to this dispute, so only a super admin can decide it.' }));

        expect(wrapper.text()).toContain('only a super admin can decide it');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});

describe('Admin/Disputes/Show, the form state', () => {
    it('keeps the decision unsent until a note is written', async () => {
        const wrapper = renderShow(review());
        await wrapper.find('input[type="radio"][value="ask_holder"]').setValue(true);
        const submit = () => wrapper.findAll('button[type="submit"]').at(-1)!;

        expect(submit().attributes('disabled')).toBeDefined();
        await wrapper.find('textarea').setValue('Send a screenshot.');
        expect(submit().attributes('disabled')).toBeUndefined();
    });

    it('links earlier disputes only where the admin may review them', () => {
        const holder = {
            ...party('holder'),
            priorDisputes: [
                {
                    ulid: 'P1',
                    tag: '#8LQ9JPYC',
                    side: 'holder',
                    status: 'resolved_denied' as const,
                    statusLabel: 'Denied',
                    openedAt: '2026-09-01T00:00:00+00:00',
                    reviewable: true,
                },
                {
                    ulid: 'P2',
                    tag: '#8LQ9JPYG',
                    side: 'claimant',
                    status: 'withdrawn' as const,
                    statusLabel: 'Withdrawn',
                    openedAt: '2026-09-02T00:00:00+00:00',
                    reviewable: false,
                },
            ],
        };
        const wrapper = renderShow(review({ holder }));

        expect(wrapper.find('a[href="/admin/disputes/P1"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/admin/disputes/P2"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('#8LQ9JPYG');
    });
});

describe('Admin/Disputes/Show, staff clean-up (P2-25)', () => {
    const suspended = (overrides: Partial<Review> = {}) =>
        review({
            status: 'resolved_suspended',
            statusLabel: 'Suspended',
            active: false,
            accountStatus: 'suspended',
            blockedReason: 'This dispute is closed.',
            canReleaseTag: true,
            ...overrides,
        });
    const button = (root: ParentNode, text: string) =>
        Array.from(root.querySelectorAll('button')).find((b) => b.textContent?.trim() === text) as HTMLButtonElement;

    it('releases the suspended tag after a note and a confirm', async () => {
        const wrapper = renderShow(suspended());
        const release = wrapper.get('section[aria-labelledby="release-heading"]');
        expect(release.get('button[type="submit"]').attributes('disabled')).toBeDefined();

        await release.get('textarea').setValue('No owner proven.');
        await release.get('form').trigger('submit');
        expect(calls).toEqual([]);

        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog.textContent).toContain('Anyone with its in-game API token can verify it next');
        button(dialog, 'Release the tag').click();
        await flushPromises();
        expect(calls).toEqual([
            { method: 'post', url: '/admin/disputes/01J0000000000000000000DISP/release-tag', data: { note: 'No owner proven.' } },
        ]);
    });

    it('keeps saying why a release was refused after the panel would have gone', async () => {
        const wrapper = renderShow(suspended());
        const release = wrapper.get('section[aria-labelledby="release-heading"]');
        await release.get('textarea').setValue('No owner proven.');
        await release.get('form').trigger('submit');
        nextErrors.value = { note: 'This tag is no longer suspended.' };
        button(document.body.querySelector('[role="dialog"]') as HTMLElement, 'Release the tag').click();
        await flushPromises();

        // Another admin released it first: the reloaded page no longer offers the release.
        await wrapper.setProps({ dispute: suspended({ canReleaseTag: false, accountStatus: 'released' }) });

        const panel = wrapper.get('section[aria-labelledby="release-heading"]');
        expect(panel.text()).toContain('This tag is no longer suspended.');
        expect(panel.find('form').exists()).toBe(false);
    });

    it('says why this admin cannot release, without a form', () => {
        const reason = 'The holder is an admin, so only a super admin can release this tag.';
        const release = renderShow(suspended({ canReleaseTag: false, releaseBlockedReason: reason })).get(
            'section[aria-labelledby="release-heading"]',
        );

        expect(release.text()).toContain(reason);
        expect(release.find('form').exists()).toBe(false);
    });

    it('has no release panel while the tag is not suspended', () => {
        expect(renderShow(review()).find('section[aria-labelledby="release-heading"]').exists()).toBe(false);
    });

    it('deletes an evidence image after a confirm, and shows what staff removed', async () => {
        const base = review();
        const evidence = base.evidence.map((entry) => (entry.images.length ? { ...entry, removable: true, removed: 1 } : entry));
        const wrapper = renderShow({ ...base, evidence });

        expect(wrapper.text()).toContain('Removed by staff: 1 image showed an identity document.');
        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Delete: ID document')!
            .trigger('click');
        expect(calls).toEqual([]);

        const dialog = document.body.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog.textContent).toContain('Delete it only if it shows an identity document.');
        button(dialog, 'Delete the image').click();
        expect(calls).toEqual([{ method: 'delete', url: '/admin/disputes/01J0000000000000000000DISP/evidence/IMG', data: {} }]);
    });

    it('offers no delete where the admin may not remove', () => {
        expect(
            renderShow(review())
                .findAll('button')
                .some((b) => b.text() === 'Delete: ID document'),
        ).toBe(false);
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
        blockedReason: 'An admin is a party to this dispute, so only a super admin can decide it.',
    };

    it('lists disputes linking to their review, with the rank note', () => {
        const wrapper = mount(Index, {
            props: { ...page, disputes: { entries: [row], nextCursor: null, previousCursor: null } },
            global: { stubs: { AdminLayout: true } },
        });

        expect(wrapper.find('a[href="/admin/disputes/01J0000000000000000000DISP"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('only a super admin can decide it');
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
