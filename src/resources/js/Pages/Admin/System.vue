<script setup lang="ts">
import AdminPanel from '@/Components/admin/AdminPanel.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import { canRetry, deleteSummary, listQuery, targetPayload, type FailedJobTarget } from '@/Components/admin/failedJobActions';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { formatDateTime, formatDuration } from '@/Composables/useDateTime';
import { formatCount } from '@/Composables/useNumberFormat';
import { useVisitError } from '@/Composables/useVisitError';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { destroy as deleteFailedJobs, retry as retryFailedJobs } from '@/routes/admin/system/failed-jobs';
import { Deferred, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AdminLayout });

type Queue = App.Domain.Operations.Data.QueueStatData;
type JobGroup = App.Domain.Operations.Data.FailedJobGroupData;
type CocKey = App.Domain.CocIntegration.Data.CocKeyData;
type FailedJobRow = App.Domain.Operations.Data.FailedJobRowData;

// specs/20 §6. Each panel is its own deferred request; the API panel's two props share one.
const props = defineProps<{
    queues?: Queue[];
    failedJobs?: App.Domain.Operations.Data.FailedJobsByClassData;
    scheduler?: App.Domain.Operations.Data.SchedulerStatusData;
    cocApiHealth?: App.Domain.CocIntegration.Data.CocApiHealthData;
    cocKeys?: CocKey[];
    canManageFailedJobs: boolean;
    failedJobsBulkMax: number;
    failedJobList?: App.Domain.Operations.Data.FailedJobListData;
}>();

const PANELS = ['queues', 'failedJobs', 'scheduler', 'cocApiHealth', 'cocKeys'] as const;

const SCHEDULER: Record<App.Domain.Operations.Enums.SchedulerState, { label: string; tone: 'success' | 'warning' | 'danger' }> = {
    running: { label: 'Running', tone: 'success' },
    stopped: { label: 'Stopped', tone: 'danger' },
    unknown: { label: 'No heartbeat recorded', tone: 'warning' },
};

const API_STATE: Record<App.Domain.CocIntegration.Enums.CocCircuitState, { label: string; tone: 'success' | 'warning' | 'danger' }> = {
    closed: { label: 'Available', tone: 'success' },
    half_open: { label: 'Recovering', tone: 'warning' },
    open: { label: 'Unavailable', tone: 'danger' },
};

function percent(rate: number): string {
    return new Intl.NumberFormat(undefined, { style: 'percent', maximumFractionDigits: 1 }).format(rate);
}

const visitError = useVisitError();

// One error per page, cleared by the next visit, so a retry reloads every panel still missing.
function retry() {
    router.reload({ only: PANELS.filter((panel) => props[panel] === undefined) });
}

const idle = computed(() => props.queues?.every((q) => q.waiting === 0 && q.delayed === 0 && q.reserved === 0) ?? false);

const queueColumns: AdminColumn[] = [
    { key: 'name', label: 'Queue', class: 'min-w-28' },
    { key: 'waiting', label: 'Waiting', class: 'w-24 text-right tabular-nums' },
    { key: 'delayed', label: 'Delayed', class: 'w-24 text-right tabular-nums' },
    { key: 'reserved', label: 'Running', class: 'w-24 text-right tabular-nums' },
    { key: 'oldestWaitSeconds', label: 'Oldest waiting', class: 'w-36 whitespace-nowrap' },
    { key: 'alerts', label: 'Alerts', class: 'min-w-48' },
];

const jobColumns: AdminColumn[] = [
    { key: 'name', label: 'Job', class: 'min-w-56' },
    { key: 'queues', label: 'Queue', class: 'w-28' },
    { key: 'count', label: 'Failures', class: 'w-24 text-right tabular-nums' },
    { key: 'lastHour', label: 'Last hour', class: 'w-24 text-right tabular-nums' },
    { key: 'firstFailedAt', label: 'First failure', class: 'w-56 whitespace-nowrap' },
    { key: 'lastFailedAt', label: 'Last failure', class: 'w-56 whitespace-nowrap' },
    { key: 'actions', label: 'Actions', class: 'w-72' },
];

// Retry and delete (P2-19), shown only with `canManageFailedJobs`; the server checks again.
// `listed` is the group whose jobs are open below the table (null name: unreadable payloads).
const listed = ref<{ name: string | null } | null>(null);
const listLoading = ref(false);
const acting = ref<string | null>(null);
const actionError = ref<string | null>(null);
const confirming = ref<FailedJobTarget | null>(null);

function groupTarget(row: JobGroup): FailedJobTarget {
    return row.name === null ? { kind: 'unreadable', count: row.count } : { kind: 'class', name: row.name, count: row.count };
}

function actionKey(target: FailedJobTarget): string {
    return target.kind === 'job' ? target.uuid : target.kind === 'class' ? `class:${target.name}` : 'unreadable';
}

function loadJobs(name: string | null) {
    listed.value = { name };
    listLoading.value = true;
    router.reload({ only: ['failedJobList'], data: listQuery(name), onFinish: () => (listLoading.value = false) });
}

function act(target: FailedJobTarget, kind: 'retry' | 'delete') {
    actionError.value = null;
    acting.value = actionKey(target);
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = null;
            if (listed.value) loadJobs(listed.value.name);
        },
        // The confirmation closes either way, so the error below the table is not hidden behind it.
        onError: (errors: Record<string, string>) => {
            confirming.value = null;
            actionError.value = Object.values(errors)[0] ?? null;
        },
        onFinish: () => (acting.value = null),
    };

    if (kind === 'retry') {
        router.post(retryFailedJobs().url, targetPayload(target), options);
    } else {
        router.delete(deleteFailedJobs().url, { ...options, data: targetPayload(target) });
    }
}

const listColumns = computed<AdminColumn[]>(() => [
    { key: 'uuid', label: 'Job id', class: 'min-w-72' },
    { key: 'queue', label: 'Queue', class: 'w-28' },
    { key: 'failedAt', label: 'Failed', class: 'w-56 whitespace-nowrap' },
    ...(props.canManageFailedJobs ? [{ key: 'actions', label: 'Actions', class: 'w-48' }] : []),
]);

const keyColumns: AdminColumn[] = [
    { key: 'id', label: 'Key', class: 'w-32' },
    { key: 'healthy', label: 'State', class: 'w-28' },
    { key: 'reason', label: 'Reason', class: 'min-w-40' },
    { key: 'unhealthySince', label: 'Since', class: 'w-56 whitespace-nowrap' },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-h2 font-semibold">System health</h1>
            <p class="text-sm text-fg-secondary">Queues, failed jobs, the scheduler and the Clash of Clans API, as of when this page loaded.</p>
        </div>

        <AdminPanel title="Queues" description="Jobs waiting for a worker, per queue.">
            <Deferred data="queues">
                <template #fallback>
                    <UiAlert v-if="visitError" kind="danger" title="Queues didn't load">
                        <p v-if="visitError.requestId">
                            If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                        </p>
                        <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                    </UiAlert>
                    <UiSkeleton v-else label="Loading queues" :lines="5" />
                </template>

                <div v-if="queues" class="flex flex-col gap-3">
                    <p v-if="idle" class="text-sm text-fg-secondary">No jobs waiting. Every queue is empty.</p>
                    <AdminTable :columns="queueColumns" :rows="queues" :row-key="(row: Queue) => row.name" caption="Queue depth and wait">
                        <template #cell-name="{ row }"><span class="text-fg">{{ row.name }}</span></template>
                        <template #cell-waiting="{ row }">{{ formatCount(row.waiting) }}</template>
                        <template #cell-delayed="{ row }">{{ formatCount(row.delayed) }}</template>
                        <template #cell-reserved="{ row }">{{ formatCount(row.reserved) }}</template>
                        <template #cell-oldestWaitSeconds="{ row }">
                            <span v-if="row.oldestWaitSeconds === null" class="text-fg-muted">None waiting</span>
                            <span v-else>{{ formatDuration(row.oldestWaitSeconds) }}</span>
                        </template>
                        <template #cell-alerts="{ row }">
                            <span class="flex flex-wrap gap-1">
                                <UiPill v-if="row.overWait && row.maxWaitSeconds !== null" tone="danger" :label="`Waiting over ${formatDuration(row.maxWaitSeconds)}`" />
                                <UiPill v-if="row.overDepth" tone="danger" :label="`Over ${formatCount(row.maxDepth)} waiting`" />
                                <span v-if="!row.overWait && !row.overDepth" class="text-fg-muted">None</span>
                            </span>
                        </template>
                    </AdminTable>
                </div>
            </Deferred>
        </AdminPanel>

        <div class="grid gap-4 lg:grid-cols-2">
            <AdminPanel title="Scheduler" description="The scheduler records a heartbeat every minute.">
                <Deferred data="scheduler">
                    <template #fallback>
                        <UiAlert v-if="visitError" kind="danger" title="The scheduler panel didn't load">
                            <p v-if="visitError.requestId">
                                If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                            </p>
                            <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                        </UiAlert>
                        <UiSkeleton v-else label="Loading the scheduler panel" :lines="2" />
                    </template>

                    <div v-if="scheduler" class="flex flex-col gap-2 text-sm">
                        <UiPill class="self-start" :tone="SCHEDULER[scheduler.state].tone" :label="SCHEDULER[scheduler.state].label" />
                        <p v-if="scheduler.lastBeatAt !== null && scheduler.ageSeconds !== null" class="text-fg-secondary">
                            Last heartbeat {{ formatDuration(scheduler.ageSeconds) }} ago,
                            <time :datetime="scheduler.lastBeatAt">{{ formatDateTime(scheduler.lastBeatAt) }}</time>.
                            <template v-if="scheduler.state === 'stopped'">It counts as stopped after {{ formatDuration(scheduler.maxAgeSeconds) }}.</template>
                        </p>
                        <p v-else class="text-fg-secondary">None yet. Scheduled jobs may not be running; check the scheduler container.</p>
                    </div>
                </Deferred>
            </AdminPanel>

            <AdminPanel title="Clash of Clans API" description="The circuit breaker, the account sync and every API key.">
                <Deferred data="cocApiHealth">
                    <template #fallback>
                        <UiAlert v-if="visitError" kind="danger" title="The API panel didn't load">
                            <p v-if="visitError.requestId">
                                If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                            </p>
                            <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                        </UiAlert>
                        <UiSkeleton v-else label="Loading the API panel" :lines="3" />
                    </template>

                    <div v-if="cocApiHealth && cocKeys" class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <UiPill :tone="API_STATE[cocApiHealth.state].tone" :label="API_STATE[cocApiHealth.state].label" />
                            <span v-if="cocApiHealth.reason" class="text-fg-secondary">
                                {{ cocApiHealth.reason === 'maintenance' ? 'Maintenance' : 'Too many failures' }}
                            </span>
                            <span v-if="cocApiHealth.state === 'open' && cocApiHealth.openUntil" class="text-fg-secondary">
                                next try <time :datetime="cocApiHealth.openUntil">{{ formatDateTime(cocApiHealth.openUntil) }}</time>
                            </span>
                            <span class="text-fg-secondary">· {{ cocApiHealth.keysHealthy }} of {{ cocApiHealth.keysTotal }} keys healthy</span>
                        </div>

                        <div class="flex flex-col gap-1 text-sm">
                            <p v-if="cocApiHealth.syncAttempts === 0" class="text-fg-secondary">No account syncs in the last {{ cocApiHealth.syncWindowMinutes }} minutes.</p>
                            <p v-else class="flex flex-wrap items-center gap-2 text-fg-secondary">
                                <span>
                                    Account syncs, {{ cocApiHealth.syncWindowMinutes }} min:
                                    <span class="text-fg">{{ percent(cocApiHealth.syncSuccessRate ?? 0) }}</span>
                                    of {{ formatCount(cocApiHealth.syncAttempts) }} succeeded
                                </span>
                                <UiPill v-if="cocApiHealth.syncBelowAlert" tone="danger" :label="`Under ${percent(cocApiHealth.syncAlert)}`" />
                            </p>
                            <p v-if="cocApiHealth.syncStopped > 0" class="text-fg-secondary">
                                {{ formatCount(cocApiHealth.syncStopped) }} {{ cocApiHealth.syncStopped === 1 ? 'account' : 'accounts' }} stopped syncing after repeated failures.
                            </p>
                        </div>
                        <p v-if="cocKeys.length === 0" class="text-sm text-fg-secondary">
                            No API keys configured. Without keys, only the fixture client answers.
                        </p>
                        <AdminTable v-else :columns="keyColumns" :rows="cocKeys" :row-key="(row: CocKey) => row.id" caption="API keys by id">
                            <template #cell-id="{ row }"><span class="text-fg">{{ row.id }}</span></template>
                            <template #cell-healthy="{ row }">
                                <UiPill :tone="row.healthy ? 'success' : 'danger'" :label="row.healthy ? 'Healthy' : 'Unhealthy'" />
                            </template>
                            <template #cell-reason="{ row }">
                                <span v-if="row.reason" class="break-all">{{ row.reason }}</span>
                                <span v-else class="text-fg-muted">None</span>
                            </template>
                            <template #cell-unhealthySince="{ row }">
                                <time v-if="row.unhealthySince" :datetime="row.unhealthySince">{{ formatDateTime(row.unhealthySince) }}</time>
                                <span v-else class="text-fg-muted">None</span>
                            </template>
                        </AdminTable>
                    </div>
                </Deferred>
            </AdminPanel>
        </div>

        <AdminPanel title="Failed jobs" description="Jobs that used up their retries, grouped by job.">
            <Deferred data="failedJobs">
                <template #fallback>
                    <UiAlert v-if="visitError" kind="danger" title="Failed jobs didn't load">
                        <p v-if="visitError.requestId">
                            If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                        </p>
                        <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                    </UiAlert>
                    <UiSkeleton v-else label="Loading failed jobs" :lines="5" />
                </template>

                <div v-if="failedJobs" class="flex flex-col gap-3">
                    <dl class="grid grid-cols-2 gap-3 sm:max-w-md">
                        <div class="flex flex-col gap-1">
                            <dt class="text-sm text-fg-secondary">Last hour</dt>
                            <dd class="text-h3 text-fg tabular-nums">{{ formatCount(failedJobs.lastHour) }}</dd>
                            <dd v-if="failedJobs.overThreshold">
                                <UiPill tone="danger" :label="`Over ${failedJobs.alertPerHour} an hour`" />
                            </dd>
                        </div>
                        <div class="flex flex-col gap-1">
                            <dt class="text-sm text-fg-secondary">Last {{ failedJobs.retentionDays }} days</dt>
                            <dd class="text-h3 text-fg tabular-nums">{{ formatCount(failedJobs.total) }}</dd>
                        </div>
                    </dl>

                    <p v-if="failedJobs.classes.length === 0" class="text-sm text-fg-secondary">
                        No failed jobs in the last {{ failedJobs.retentionDays }} days.
                    </p>
                    <AdminTable
                        v-else
                        :columns="jobColumns"
                        :rows="failedJobs.classes"
                        :row-key="(row: JobGroup) => row.name ?? ''"
                        :caption="`Failed jobs by job in the last ${failedJobs.retentionDays} days, most failures first`"
                    >
                        <template #cell-name="{ row }">
                            <span v-if="row.name" class="break-all text-fg">{{ row.name }}</span>
                            <span v-else class="text-fg-muted">Unreadable job payload</span>
                        </template>
                        <template #cell-queues="{ row }">{{ row.queues.join(', ') }}</template>
                        <template #cell-count="{ row }">{{ formatCount(row.count) }}</template>
                        <template #cell-lastHour="{ row }">{{ formatCount(row.lastHour) }}</template>
                        <template #cell-firstFailedAt="{ row }">
                            <time :datetime="row.firstFailedAt">{{ formatDateTime(row.firstFailedAt) }}</time>
                        </template>
                        <template #cell-lastFailedAt="{ row }">
                            <time :datetime="row.lastFailedAt">{{ formatDateTime(row.lastFailedAt) }}</time>
                        </template>
                        <template #cell-actions="{ row }">
                            <span class="flex flex-wrap gap-2">
                                <UiButton size="sm" variant="ghost" @click="loadJobs(row.name)">Show jobs</UiButton>
                                <template v-if="canManageFailedJobs">
                                    <UiButton
                                        v-if="canRetry(groupTarget(row))"
                                        size="sm"
                                        variant="secondary"
                                        :loading="acting === actionKey(groupTarget(row))"
                                        :disabled="acting !== null"
                                        @click="act(groupTarget(row), 'retry')"
                                    >
                                        Retry all
                                    </UiButton>
                                    <UiButton size="sm" variant="ghost" :disabled="acting !== null" @click="confirming = groupTarget(row)">Delete all</UiButton>
                                </template>
                            </span>
                        </template>
                    </AdminTable>

                    <UiAlert v-if="actionError" kind="danger" title="That didn't work">{{ actionError }}</UiAlert>
                    <UiAlert v-else-if="visitError && acting === null && failedJobs" kind="danger" title="The action didn't finish">
                        <template v-if="visitError.throttled">Too many actions in a row. Wait a minute and try again.</template>
                        <template v-else-if="visitError.requestId">
                            If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                        </template>
                        <template v-else>Check your connection and try again.</template>
                    </UiAlert>

                    <section v-if="listed" aria-labelledby="failed-job-list" class="flex flex-col gap-3 border-t border-line pt-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 id="failed-job-list" class="text-sm font-semibold text-fg">
                                {{ listed.name ?? 'Jobs with an unreadable payload' }}
                            </h3>
                            <UiButton size="sm" variant="ghost" @click="listed = null">Close</UiButton>
                        </div>
                        <UiSkeleton v-if="listLoading || !failedJobList" label="Loading the jobs" :lines="3" />
                        <template v-else>
                            <p class="text-sm text-fg-secondary">
                                <template v-if="failedJobList.total > failedJobList.jobs.length">
                                    The newest {{ formatCount(failedJobList.jobs.length) }} of {{ formatCount(failedJobList.total) }}.
                                </template>
                                <template v-else-if="failedJobList.total === 0">None left.</template>
                            </p>
                            <AdminTable
                                v-if="failedJobList.jobs.length"
                                :columns="listColumns"
                                :rows="failedJobList.jobs"
                                :row-key="(row: FailedJobRow) => row.uuid"
                                caption="Failed jobs of this kind, newest first"
                            >
                                <template #cell-uuid="{ row }"><span class="break-all font-mono text-xs">{{ row.uuid }}</span></template>
                                <template #cell-failedAt="{ row }">
                                    <time :datetime="row.failedAt">{{ formatDateTime(row.failedAt) }}</time>
                                </template>
                                <template #cell-actions="{ row }">
                                    <span class="flex flex-wrap gap-2">
                                        <UiButton
                                            v-if="listed.name !== null"
                                            size="sm"
                                            variant="secondary"
                                            :loading="acting === row.uuid"
                                            :disabled="acting !== null"
                                            @click="act({ kind: 'job', uuid: row.uuid, name: listed.name }, 'retry')"
                                        >
                                            Retry
                                        </UiButton>
                                        <UiButton
                                            size="sm"
                                            variant="ghost"
                                            :disabled="acting !== null"
                                            @click="confirming = { kind: 'job', uuid: row.uuid, name: listed.name }"
                                        >
                                            Delete
                                        </UiButton>
                                    </span>
                                </template>
                            </AdminTable>
                        </template>
                    </section>
                </div>
            </Deferred>
        </AdminPanel>

        <UiModal
            v-if="canManageFailedJobs"
            :open="confirming !== null"
            title="Delete failed jobs?"
            @update:open="(open: boolean) => !open && (confirming = null)"
        >
            <div v-if="confirming" class="flex flex-col gap-4">
                <p class="text-body text-fg-secondary">{{ deleteSummary(confirming, failedJobsBulkMax) }}</p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UiButton variant="secondary" :disabled="acting !== null" @click="confirming = null">Cancel</UiButton>
                    <UiButton variant="danger" :loading="acting !== null" @click="act(confirming, 'delete')">Delete</UiButton>
                </div>
            </div>
        </UiModal>
    </div>
</template>
