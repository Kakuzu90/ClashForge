import System from '@/Pages/Admin/System.vue';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

type Handler = (event: { detail: Record<string, unknown>; preventDefault: () => void }) => void;
const handlers: Record<string, Handler> = {};

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, inject } = await import('vue');
    return {
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
        usePage: () => ({ url: '/admin/system', props: { auth: { user: null, can: {} } } }),
    };
});

type Queue = App.Domain.Operations.Data.QueueStatData;

const queue = (overrides: Partial<Queue> = {}): Queue => ({
    name: 'default',
    waiting: 0,
    delayed: 0,
    reserved: 0,
    oldestWaitSeconds: null,
    maxWaitSeconds: 1800,
    maxDepth: 500,
    overWait: false,
    overDepth: false,
    ...overrides,
});

const failedJobs = (overrides: Partial<App.Domain.Operations.Data.FailedJobsByClassData> = {}): App.Domain.Operations.Data.FailedJobsByClassData => ({
    total: 0,
    lastHour: 0,
    alertPerHour: 20,
    overThreshold: false,
    retentionDays: 30,
    classes: [],
    ...overrides,
});

const cocApiHealth: App.Domain.CocIntegration.Data.CocApiHealthData = {
    state: 'closed',
    reason: null,
    openUntil: null,
    keysHealthy: 1,
    keysTotal: 2,
    windowHours: 24,
    calls: 10,
    cacheHits: 5,
    failures: 0,
    failureRate: 0,
    topError: null,
    syncWindowMinutes: 30,
    syncAttempts: 0,
    syncSuccesses: 0,
    syncSuccessRate: null,
    syncAlert: 0.9,
    syncBelowAlert: false,
    syncStopped: 0,
};

type Props = InstanceType<typeof System>['$props'];

const render = (props: Props, loaded: string[] = []) => mount(System, { props, global: { provide: { loaded } } });

function fail(requestId: string) {
    handlers.invalid?.({ detail: { response: { status: 500, headers: { 'x-request-id': requestId } } }, preventDefault: vi.fn() });
}

describe('Admin/System', () => {
    it('shows a skeleton per panel while they load', () => {
        expect(render({}).findAll('[role="status"]').map((node) => node.text())).toEqual([
            'Loading queues',
            'Loading the scheduler panel',
            'Loading the API panel',
            'Loading failed jobs',
        ]);
    });

    it('says when every queue is empty, nothing has failed, the heartbeat is missing and no keys exist', () => {
        const text = render(
            {
                queues: [queue({ name: 'high', maxWaitSeconds: 300 }), queue()],
                failedJobs: failedJobs(),
                scheduler: { state: 'unknown', lastBeatAt: null, ageSeconds: null, maxAgeSeconds: 300 },
                cocApiHealth: { ...cocApiHealth, keysHealthy: 0, keysTotal: 0 },
                cocKeys: [],
            },
            ['queues', 'failedJobs', 'scheduler', 'cocApiHealth'],
        ).text();

        expect(text).toContain('No jobs waiting. Every queue is empty.');
        expect(text).toContain('None waiting');
        expect(text).toContain('No failed jobs in the last 30 days.');
        expect(text).toContain('No heartbeat recorded');
        expect(text).toContain('None yet.');
        expect(text).toContain('No API keys configured.');
    });

    it('flags a long wait and a deep queue in words', () => {
        const text = render(
            {
                queues: [
                    queue({ name: 'high', waiting: 3, oldestWaitSeconds: 420, maxWaitSeconds: 300, overWait: true }),
                    queue({ name: 'low', waiting: 612, oldestWaitSeconds: 30, maxWaitSeconds: null, overDepth: true }),
                ],
            },
            ['queues'],
        ).text();

        expect(text).toContain('7 min');
        expect(text).toContain('Waiting over 5 min');
        expect(text).toContain('Over 500 waiting');
        expect(text).not.toContain('Every queue is empty');
    });

    it('lists failed jobs by class with their queues, and flags the hour over the line', () => {
        const text = render(
            {
                failedJobs: failedJobs({
                    total: 31,
                    lastHour: 21,
                    overThreshold: true,
                    classes: [
                        { name: 'App\\Jobs\\SendMail', queues: ['high', 'low'], count: 30, lastHour: 21, firstFailedAt: '2026-09-30T10:00:00+00:00', lastFailedAt: '2026-10-02T11:59:00+00:00' },
                        { name: null, queues: ['default'], count: 1, lastHour: 0, firstFailedAt: '2026-10-01T10:00:00+00:00', lastFailedAt: '2026-10-01T10:00:00+00:00' },
                    ],
                }),
            },
            ['failedJobs'],
        ).text();

        expect(text).toContain('Over 20 an hour');
        expect(text).toContain('App\\Jobs\\SendMail');
        expect(text).toContain('high, low');
        expect(text).toContain('Unreadable job payload');
        expect(text).toContain('Last 30 days');
    });

    it('says how long ago the scheduler ran, and when it counts as stopped', () => {
        const running = render({ scheduler: { state: 'running', lastBeatAt: '2026-10-02T11:59:30+00:00', ageSeconds: 30, maxAgeSeconds: 300 } }, ['scheduler']).text();
        const stopped = render({ scheduler: { state: 'stopped', lastBeatAt: '2026-10-02T11:00:00+00:00', ageSeconds: 3600, maxAgeSeconds: 300 } }, ['scheduler']).text();

        expect(running).toContain('Running');
        expect(running).toContain('Last heartbeat 30 s ago');
        expect(running).not.toContain('counts as stopped');
        expect(stopped).toContain('Stopped');
        expect(stopped).toContain('Last heartbeat 1 h ago');
        expect(stopped).toContain('It counts as stopped after 5 min.');
    });

    it('lists each API key with its state and reason', () => {
        const text = render(
            {
                cocApiHealth,
                cocKeys: [
                    { id: 'a1b2c3d4', healthy: true, reason: null, unhealthySince: null },
                    { id: 'e5f6a7b8', healthy: false, reason: 'accessDenied.invalidIp', unhealthySince: '2026-10-02T11:00:00+00:00' },
                ],
            },
            ['cocApiHealth'],
        ).text();

        expect(text).toContain('1 of 2 keys healthy');
        expect(text).toContain('No account syncs in the last 30 minutes.');
        expect(text).toContain('a1b2c3d4');
        expect(text).toContain('Healthy');
        expect(text).toContain('Unhealthy');
        expect(text).toContain('accessDenied.invalidIp');
    });

    it('shows the error with its request id in the panels that did not load, and retries every missing one', async () => {
        const wrapper = render({ queues: [queue()] }, ['queues']);

        fail('req-sys-1');
        await nextTick();

        const alerts = wrapper.findAll('[role="alert"]').map((node) => node.text());
        expect(alerts).toHaveLength(3);
        expect(alerts[0]).toContain("The scheduler panel didn't load");
        expect(alerts[2]).toContain("Failed jobs didn't load");
        expect(alerts[2]).toContain('req-sys-1');

        await wrapper.findAll('button').find((button) => button.text() === 'Try again')!.trigger('click');
        expect(router.reload).toHaveBeenCalledWith({ only: ['failedJobs', 'scheduler', 'cocApiHealth', 'cocKeys'] });
    });
});
