import Dashboard from '@/Pages/Admin/Dashboard.vue';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

type Handler = (event: { detail: Record<string, unknown>; preventDefault: () => void }) => void;
const handlers: Record<string, Handler> = {};

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, inject } = await import('vue');
    return {
        // Renders its content once the page has the prop, like Inertia's own.
        Deferred: defineComponent({
            props: { data: { type: String, required: true } },
            setup(props, { slots }) {
                const loaded = inject<string[]>('loaded', []);
                return () => (loaded.includes(props.data) ? slots.default?.() : slots.fallback?.());
            },
        }),
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        router: {
            on: (name: string, handler: Handler) => {
                handlers[name] = handler;
                return () => delete handlers[name];
            },
            reload: vi.fn(),
        },
        usePage: () => ({ url: '/admin', props: { auth: { user: null, can: {} } } }),
    };
});

type Props = App.Http.Data.Admin.AdminDashboardPageData & {
    signups?: App.Domain.Auth.Data.SignupStatsData;
    failedJobs?: App.Support.Health.FailedJobsSummaryData;
    storage?: App.Domain.Media.Data.MediaStorageData;
};

const signups: App.Domain.Auth.Data.SignupStatsData = {
    last24Hours: { total: 3, verified: 2 },
    last7Days: { total: 40, verified: 31 },
    last30Days: { total: 1204, verified: 1000 },
};

const failedJobs = (overrides: Partial<App.Support.Health.FailedJobsSummaryData> = {}): App.Support.Health.FailedJobsSummaryData => ({
    lastHour: 0,
    last24Hours: 0,
    alertPerHour: 20,
    overThreshold: false,
    topClasses: [],
    ...overrides,
});

const storage: App.Domain.Media.Data.MediaStorageData = {
    totalBytes: 1_450_000_000,
    totalObjects: 3200,
    collections: [{ collection: 'avatar', label: 'Avatar', bytes: 12_500_000, objects: 800 }],
    awaitingPurgeBytes: 2_000_000,
    awaitingPurgeObjects: 4,
    purgeAfterDays: 7,
    quarantinedCount: 1,
};

const render = (props: Props, loaded: string[] = []) => mount(Dashboard, { props, global: { provide: { loaded } } });

function fail(requestId: string) {
    handlers.invalid?.({ detail: { response: { status: 500, headers: { 'x-request-id': requestId } } }, preventDefault: vi.fn() });
}

describe('Admin/Dashboard', () => {
    it('tells a moderator what will arrive instead of showing panels', () => {
        const text = render({ platformStats: false }).text();

        expect(text).toContain('Nothing to review yet');
        expect(text).not.toContain('New sign-ups');
    });

    it('shows a skeleton per panel while they load', () => {
        const wrapper = render({ platformStats: true });

        expect(wrapper.findAll('[role="status"]').map((node) => node.text())).toEqual(['Loading sign-ups', 'Loading failed jobs', 'Loading media storage']);
    });

    it('shows the counts, sizes and verified share once loaded', () => {
        const text = render({ platformStats: true, signups, failedJobs: failedJobs(), storage }, ['signups', 'failedJobs', 'storage']).text();

        expect(text).toContain('1,204');
        expect(text).toContain('1,000 verified');
        expect(text).toContain('1.5 GB');
        expect(text).toContain('3,200 files');
        expect(text).toContain('4 files, removed after 7 days');
        expect(text).toContain('12.5 MB');
        expect(text).toContain('No failed jobs in the last 24 hours.');
    });

    it('flags the failure rate over the alert line and lists the job classes', () => {
        const summary = failedJobs({
            lastHour: 21,
            last24Hours: 30,
            overThreshold: true,
            topClasses: [
                { name: 'App\\Jobs\\SendMail', count: 29, lastFailedAt: '2026-10-01T11:59:00+00:00' },
                { name: null, count: 1, lastFailedAt: '2026-10-01T11:00:00+00:00' },
            ],
        });
        const text = render({ platformStats: true, failedJobs: summary }, ['failedJobs']).text();

        expect(text).toContain('Over 20 an hour');
        expect(text).toContain('App\\Jobs\\SendMail');
        expect(text).toContain('Unreadable job payload');
        expect(text).not.toContain('No failed jobs');
    });

    it('does not flag a rate at or under the line', () => {
        expect(render({ platformStats: true, failedJobs: failedJobs({ lastHour: 20 }) }, ['failedJobs']).text()).not.toContain('an hour');
    });

    it('shows the error with its request id in the panels that did not load, and keeps the loaded ones', async () => {
        const wrapper = render({ platformStats: true, signups }, ['signups']);

        fail('req-dash-1');
        await nextTick();

        const alerts = wrapper.findAll('[role="alert"]').map((node) => node.text());
        expect(alerts).toHaveLength(2);
        expect(alerts[0]).toContain("Failed jobs didn't load");
        expect(alerts[1]).toContain("Media storage didn't load");
        expect(alerts[1]).toContain('req-dash-1');
        expect(wrapper.text()).toContain('1,204');
    });

    it('retries every panel still missing, not just the one whose button was pressed', async () => {
        const wrapper = render({ platformStats: true, signups }, ['signups']);

        fail('req-dash-2');
        await nextTick();
        await wrapper.findAll('button').find((button) => button.text() === 'Try again')!.trigger('click');

        expect(router.reload).toHaveBeenCalledWith({ only: ['failedJobs', 'storage'] });
    });
});
