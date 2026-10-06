<script setup lang="ts">
import AdminFilterBar from '@/Components/admin/AdminFilterBar.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCheckbox from '@/Components/ui/UiCheckbox.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiSelect from '@/Components/ui/UiSelect.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { useNavigating } from '@/Composables/useNavigating';
import { useVisitError } from '@/Composables/useVisitError';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { index, show } from '@/routes/admin/disputes';
import { Deferred, Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

defineOptions({ layout: AdminLayout });

type Page = App.Http.Data.Admin.AdminDisputeIndexPageData;
type Row = App.Domain.PlayerAccounts.Data.DisputeQueueRowData;

// FR-ADMIN-2 (disputes): running disputes, the longest wait first. Statements and evidence stay on
// the review page.
const props = defineProps<{
    view: Page['view'];
    mine: Page['mine'];
    views: Page['views'];
    disputes?: App.Domain.PlayerAccounts.Data.DisputeQueueData;
}>();

const DEFAULT_VIEW = 'active';
const fields = reactive({ view: props.view, mine: props.mine });
watch(
    () => [props.view, props.mine] as const,
    ([view, mine]) => {
        fields.view = view;
        fields.mine = mine;
    },
);

const hasFilters = computed(() => props.view !== DEFAULT_VIEW || props.mine);
const navigating = useNavigating((url) => url.pathname === index().url);
const visitError = useVisitError();

function query(extra: Record<string, string> = {}): Record<string, string> {
    return {
        ...(fields.view !== DEFAULT_VIEW ? { view: fields.view } : {}),
        ...(fields.mine ? { mine: '1' } : {}),
        ...extra,
    };
}

function visit(params: Record<string, string>) {
    router.get(index().url, params, { preserveState: true, preserveScroll: true });
}

function clear() {
    fields.view = DEFAULT_VIEW;
    fields.mine = false;
    visit({});
}

const STATUS_TONE: Record<string, 'warning' | 'info' | 'neutral' | 'success' | 'danger'> = {
    awaiting_admin: 'warning',
    open: 'info',
    awaiting_holder: 'info',
    awaiting_claimant: 'info',
    resolved_transfer: 'success',
    resolved_suspended: 'danger',
};

const columns: AdminColumn[] = [
    { key: 'tag', label: 'Account', class: 'min-w-36' },
    { key: 'parties', label: 'Claimant → holder', class: 'min-w-56' },
    { key: 'status', label: 'Status', class: 'w-56 whitespace-nowrap' },
    { key: 'waiting', label: 'Waiting since', class: 'w-56 whitespace-nowrap' },
    { key: 'assigned', label: 'Assigned', class: 'w-40' },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-h2 font-semibold">Disputes</h1>
            <p class="text-sm text-fg-secondary">Ownership disputes, the longest wait first. Disputes you are part of are not listed.</p>
        </div>

        <AdminFilterBar label="Filter disputes" :busy="navigating" :can-clear="hasFilters" @apply="visit(query())" @clear="clear">
            <UiSelect v-model="fields.view" label="Show" :options="views" />
            <UiCheckbox v-model="fields.mine" label="Assigned to me" />
        </AdminFilterBar>

        <UiAlert v-if="visitError?.throttled" kind="warning" title="Too many searches in a minute">
            <p>Wait a moment, then try again.</p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>
        <UiAlert v-else-if="visitError" kind="danger" title="The dispute queue didn't load">
            <p>
                Try again in a moment.<template v-if="visitError.requestId">
                    If it keeps failing, quote request id <span class="font-semibold text-fg">{{ visitError.requestId }}</span
                    >.</template
                >
            </p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>

        <Deferred data="disputes">
            <template #fallback>
                <AdminTable v-if="!visitError" :columns="columns" :rows="[]" :row-key="() => 0" caption="Disputes" loading />
            </template>

            <div class="flex flex-col gap-3">
                <AdminTable
                    :columns="columns"
                    :rows="disputes?.entries ?? []"
                    :row-key="(row: Row) => row.ulid"
                    caption="Disputes"
                    :loading="navigating"
                >
                    <template #cell-tag="{ row }">
                        <Link :href="show(row.ulid).url" class="font-mono font-semibold text-fg underline-offset-2 hover:underline">{{
                            row.tag
                        }}</Link>
                    </template>
                    <template #cell-parties="{ row }">
                        <span class="break-all text-fg">{{ row.claimant }}</span>
                        <span class="text-fg-muted"> → </span>
                        <span class="break-all text-fg">{{ row.holder ?? 'nobody' }}</span>
                    </template>
                    <template #cell-status="{ row }">
                        <div class="flex flex-col items-start gap-1">
                            <UiPill :label="row.statusLabel" :tone="STATUS_TONE[row.status] ?? 'neutral'" />
                            <span v-if="row.blockedReason" class="text-sm text-fg-secondary">{{ row.blockedReason }}</span>
                        </div>
                    </template>
                    <template #cell-waiting="{ row }">
                        <time :datetime="row.waitingSince">{{ formatDateTime(row.waitingSince) }}</time>
                    </template>
                    <template #cell-assigned="{ row }">
                        <span v-if="row.assignedTo" class="break-all">{{ row.assignedTo }}</span>
                        <span v-else class="text-fg-muted">Nobody</span>
                    </template>
                    <template #empty>{{ hasFilters ? 'No disputes match.' : 'No disputes waiting.' }}</template>
                </AdminTable>

                <nav v-if="disputes && (disputes.previousCursor || disputes.nextCursor)" aria-label="Dispute pages" class="flex flex-wrap gap-2">
                    <UiButton
                        v-if="disputes.previousCursor"
                        variant="secondary"
                        size="sm"
                        :disabled="navigating"
                        @click="visit(query({ cursor: disputes.previousCursor }))"
                        >Previous</UiButton
                    >
                    <UiButton
                        v-if="disputes.nextCursor"
                        variant="secondary"
                        size="sm"
                        :disabled="navigating"
                        @click="visit(query({ cursor: disputes.nextCursor }))"
                        >Next</UiButton
                    >
                </nav>
            </div>
        </Deferred>
    </div>
</template>
