<script setup lang="ts">
import AdminPanel from '@/Components/admin/AdminPanel.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { formatBytes, formatCount } from '@/Composables/useNumberFormat';
import { useVisitError } from '@/Composables/useVisitError';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { home } from '@/routes';
import { system } from '@/routes/admin';
import { Deferred, router } from '@inertiajs/vue3';

defineOptions({ layout: AdminLayout });

type JobClass = App.Domain.Operations.Data.FailedJobClassData;
type CollectionUsage = App.Domain.Media.Data.MediaCollectionUsageData;

// FR-ADMIN-5. Each panel is its own deferred request: one that fails shows its error in place
// while the others render.
const props = defineProps<{
    platformStats: App.Http.Data.Admin.AdminDashboardPageData['platformStats'];
    signups?: App.Domain.Auth.Data.SignupStatsData;
    failedJobs?: App.Domain.Operations.Data.FailedJobsSummaryData;
    storage?: App.Domain.Media.Data.MediaStorageData;
    cocApiHealth?: App.Domain.CocIntegration.Data.CocApiHealthData;
}>();

const PANELS = ['signups', 'failedJobs', 'storage', 'cocApiHealth'] as const;

const API_STATE: Record<App.Domain.CocIntegration.Enums.CocCircuitState, { label: string; tone: 'success' | 'warning' | 'danger' }> = {
    closed: { label: 'Available', tone: 'success' },
    half_open: { label: 'Recovering', tone: 'warning' },
    open: { label: 'Unavailable', tone: 'danger' },
};

function percent(rate: number): string {
    return new Intl.NumberFormat(undefined, { style: 'percent', maximumFractionDigits: 1 }).format(rate);
}

const visitError = useVisitError();

// The error is one per page, and the next visit clears it everywhere, so a retry reloads every
// panel still missing rather than the one whose button was pressed.
function retry() {
    router.reload({ only: PANELS.filter((panel) => props[panel] === undefined) });
}

const jobColumns: AdminColumn[] = [
    { key: 'name', label: 'Job', class: 'min-w-56' },
    { key: 'count', label: 'Failures', class: 'w-24 text-right tabular-nums' },
    { key: 'lastFailedAt', label: 'Last failure', class: 'w-56 whitespace-nowrap' },
];

const storageColumns: AdminColumn[] = [
    { key: 'label', label: 'Collection', class: 'min-w-40' },
    { key: 'bytes', label: 'Size', class: 'w-28 text-right tabular-nums whitespace-nowrap' },
    { key: 'objects', label: 'Files', class: 'w-24 text-right tabular-nums' },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-h2 font-semibold">Dashboard</h1>
            <UiButton variant="ghost" size="sm" :href="home().url">Back to site</UiButton>
        </div>

        <UiEmptyState
            v-if="!platformStats"
            title="Nothing to review yet"
            body="Open reports and the moderation queue will show here once reporting goes live."
        />

        <div v-else class="grid gap-4 lg:grid-cols-2">
            <AdminPanel title="New sign-ups" description="Accounts created, deleted ones included.">
                <Deferred data="signups">
                    <template #fallback>
                        <UiAlert v-if="visitError" kind="danger" title="Sign-ups didn't load">
                            <p v-if="visitError.requestId">
                                If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                            </p>
                            <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                        </UiAlert>
                        <UiSkeleton v-else label="Loading sign-ups" :lines="3" />
                    </template>

                    <dl v-if="signups" class="grid grid-cols-3 gap-3">
                        <div
                            v-for="window in [
                                { label: 'Last 24 hours', count: signups.last24Hours },
                                { label: 'Last 7 days', count: signups.last7Days },
                                { label: 'Last 30 days', count: signups.last30Days },
                            ]"
                            :key="window.label"
                            class="flex flex-col gap-1"
                        >
                            <dt class="text-sm text-fg-secondary">{{ window.label }}</dt>
                            <dd class="text-h3 text-fg tabular-nums">{{ formatCount(window.count.total) }}</dd>
                            <dd class="text-sm text-fg-secondary">{{ formatCount(window.count.verified) }} verified</dd>
                        </div>
                    </dl>
                </Deferred>
            </AdminPanel>

            <AdminPanel title="Failed jobs" description="Jobs that used up their retries.">
                <Deferred data="failedJobs">
                    <template #fallback>
                        <UiAlert v-if="visitError" kind="danger" title="Failed jobs didn't load">
                            <p v-if="visitError.requestId">
                                If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                            </p>
                            <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                        </UiAlert>
                        <UiSkeleton v-else label="Loading failed jobs" :lines="3" />
                    </template>

                    <div v-if="failedJobs" class="flex flex-col gap-3">
                        <dl class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Last hour</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatCount(failedJobs.lastHour) }}</dd>
                                <dd v-if="failedJobs.overThreshold">
                                    <UiPill tone="danger" :label="`Over ${failedJobs.alertPerHour} an hour`" />
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Last 24 hours</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatCount(failedJobs.last24Hours) }}</dd>
                            </div>
                        </dl>

                        <p v-if="failedJobs.topClasses.length === 0" class="text-sm text-fg-secondary">No failed jobs in the last 24 hours.</p>
                        <AdminTable
                            v-else
                            :columns="jobColumns"
                            :rows="failedJobs.topClasses"
                            :row-key="(row: JobClass) => `${row.name}-${row.lastFailedAt}`"
                            caption="Most failed jobs in the last 24 hours"
                        >
                            <template #cell-name="{ row }">
                                <span v-if="row.name" class="break-all text-fg">{{ row.name }}</span>
                                <span v-else class="text-fg-muted">Unreadable job payload</span>
                            </template>
                            <template #cell-count="{ row }">{{ formatCount(row.count) }}</template>
                            <template #cell-lastFailedAt="{ row }">
                                <time :datetime="row.lastFailedAt">{{ formatDateTime(row.lastFailedAt) }}</time>
                            </template>
                        </AdminTable>
                        <UiButton class="self-start" variant="secondary" size="sm" :href="system().url">Open System health</UiButton>
                    </div>
                </Deferred>
            </AdminPanel>

            <AdminPanel title="Clash of Clans API" description="Our calls to the game API. The sync success rate joins once syncing starts.">
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

                    <div v-if="cocApiHealth" class="flex flex-col gap-3">
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
                        <p v-if="cocApiHealth.calls === 0 && cocApiHealth.cacheHits === 0" class="text-sm text-fg-secondary">
                            No calls in the last {{ cocApiHealth.windowHours }} hours.
                        </p>
                        <dl v-else class="grid grid-cols-3 gap-3">
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Calls, {{ cocApiHealth.windowHours }} h</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatCount(cocApiHealth.calls) }}</dd>
                                <dd class="text-sm text-fg-secondary">{{ formatCount(cocApiHealth.cacheHits) }} from cache</dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Failures</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatCount(cocApiHealth.failures) }}</dd>
                                <dd v-if="cocApiHealth.failureRate !== null" class="text-sm text-fg-secondary">{{ percent(cocApiHealth.failureRate) }} of calls</dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Most common error</dt>
                                <dd class="text-body break-all text-fg">{{ cocApiHealth.topError ?? 'None' }}</dd>
                            </div>
                        </dl>
                    </div>
                </Deferred>
            </AdminPanel>

            <AdminPanel class="lg:col-span-2" title="Media storage" description="Uploads and their resized copies in the bucket.">
                <Deferred data="storage">
                    <template #fallback>
                        <UiAlert v-if="visitError" kind="danger" title="Media storage didn't load">
                            <p v-if="visitError.requestId">
                                If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span>.
                            </p>
                            <UiButton class="mt-3" variant="secondary" size="sm" @click="retry()">Try again</UiButton>
                        </UiAlert>
                        <UiSkeleton v-else label="Loading media storage" :lines="4" />
                    </template>

                    <div v-if="storage" class="flex flex-col gap-3">
                        <dl class="grid gap-3 sm:grid-cols-3">
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Stored</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatBytes(storage.totalBytes) }}</dd>
                                <dd class="text-sm text-fg-secondary">{{ formatCount(storage.totalObjects) }} files</dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Deleted, awaiting purge</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatBytes(storage.awaitingPurgeBytes) }}</dd>
                                <dd class="text-sm text-fg-secondary">
                                    {{ formatCount(storage.awaitingPurgeObjects) }} files, removed after {{ storage.purgeAfterDays }} days
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1">
                                <dt class="text-sm text-fg-secondary">Held for review</dt>
                                <dd class="text-h3 text-fg tabular-nums">{{ formatCount(storage.quarantinedCount) }}</dd>
                                <dd class="text-sm text-fg-secondary">quarantined uploads</dd>
                            </div>
                        </dl>

                        <AdminTable
                            :columns="storageColumns"
                            :rows="storage.collections"
                            :row-key="(row: CollectionUsage) => row.collection"
                            caption="Storage by collection, deleted media left out"
                        >
                            <template #cell-bytes="{ row }">{{ formatBytes(row.bytes) }}</template>
                            <template #cell-objects="{ row }">{{ formatCount(row.objects) }}</template>
                        </AdminTable>
                    </div>
                </Deferred>
            </AdminPanel>
        </div>
    </div>
</template>
