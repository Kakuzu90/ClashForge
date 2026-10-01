<script setup lang="ts">
import AdminDiffViewer from '@/Components/admin/AdminDiffViewer.vue';
import AdminFilterBar from '@/Components/admin/AdminFilterBar.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiSelect, { type SelectOption } from '@/Components/ui/UiSelect.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { useNavigating } from '@/Composables/useNavigating';
import { useVisitError } from '@/Composables/useVisitError';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { audit } from '@/routes/admin';
import { Deferred, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

defineOptions({ layout: AdminLayout });

type Page = App.Http.Data.Admin.AuditLogPageData;
type Entry = App.Http.Data.Admin.AuditLogEntryData;
type Filters = Page['filters'];

// FR-ADMIN-4: the read-only audit log, newest first, filtered by actor, account, action and date.
const props = defineProps<{
    filters: Filters;
    actions: Page['actions'];
    log?: App.Http.Data.Admin.AuditLogListData;
}>();

const page = usePage();
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);

const ANY = '';
const fields = reactive({ actor: '', target: '', action: ANY, from: '', to: '' });

function fill(filters: Filters) {
    fields.actor = filters.actor ?? '';
    fields.target = filters.target ?? '';
    fields.action = filters.action ?? ANY;
    fields.from = filters.from ?? '';
    fields.to = filters.to ?? '';
}
fill(props.filters);
watch(() => props.filters, fill);

const actionOptions = computed<SelectOption[]>(() => [{ value: ANY, label: 'Any action' }, ...props.actions]);
const hasFilters = computed(() => Object.values(props.filters).some((value) => value !== null));

const navigating = useNavigating((url) => url.pathname === audit().url);
const visitError = useVisitError();

function visit(query: Record<string, string>) {
    router.get(audit().url, query, { preserveState: true, preserveScroll: true });
}

function applied(): Record<string, string> {
    return Object.fromEntries(Object.entries(fields).filter(([, value]) => value.trim() !== ''));
}

function apply() {
    visit(applied());
}

function clear() {
    fill({ actor: null, target: null, action: null, from: null, to: null });
    visit({});
}

function goTo(cursor: string) {
    // The cursor pages the filters as applied, not as edited since.
    const current = Object.fromEntries(Object.entries(props.filters).filter((entry): entry is [string, string] => entry[1] !== null));
    visit({ ...current, cursor });
}

const columns: AdminColumn[] = [
    { key: 'when', label: 'When', class: 'w-56 whitespace-nowrap' },
    { key: 'action', label: 'Action', class: 'w-40' },
    { key: 'actor', label: 'Acted by', class: 'w-48' },
    { key: 'subject', label: 'Account', class: 'min-w-40' },
];

// Actors cannot be deleted while they have entries (the FK restricts), so no username means the
// console or the scheduler acted.
function actorName(entry: Entry): string {
    if (entry.actorUsername) return entry.actorUsername;
    return entry.actorVia === 'console' ? 'Console' : 'System';
}

function subjectName(entry: Entry): string {
    return entry.subjectName ?? `${entry.subjectLabel} no longer exists`;
}

const expandLabel = (entry: Entry) => `Details: ${entry.actionLabel}, ${subjectName(entry)}, ${formatDateTime(entry.createdAt)}`;
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-h2 font-semibold">Audit log</h1>
            <p class="text-sm text-fg-secondary">Every privileged action, newest first. Entries are never edited or removed.</p>
        </div>

        <AdminFilterBar label="Filter the audit log" :busy="navigating" :can-clear="hasFilters" @apply="apply" @clear="clear">
            <UiInput v-model="fields.actor" label="Acted by" hint="Username" autocomplete="off" :maxlength="20" :error="errors.actor" />
            <UiInput v-model="fields.target" label="Account" hint="Username" autocomplete="off" :maxlength="20" :error="errors.target" />
            <UiSelect v-model="fields.action" label="Action" :options="actionOptions" :error="errors.action" />
            <UiInput v-model="fields.from" label="From" type="date" hint="UTC" :error="errors.from" />
            <UiInput v-model="fields.to" label="To" type="date" hint="UTC" :error="errors.to" />
        </AdminFilterBar>

        <UiAlert v-if="visitError?.throttled" kind="warning" title="Too many searches in a minute">
            <p>Wait a moment, then try again.</p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>
        <UiAlert v-else-if="visitError" kind="danger" title="The audit log didn't load">
            <p>
                Try again in a moment.<template v-if="visitError.requestId">
                    If it keeps failing, quote request id <span class="font-semibold text-fg">{{ visitError.requestId }}</span
                    >.</template
                >
            </p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>

        <Deferred data="log">
            <template #fallback>
                <AdminTable v-if="!visitError" :columns="columns" :rows="[]" :row-key="() => 0" caption="Audit log entries" loading expandable />
            </template>

            <div class="flex flex-col gap-3">
                <AdminTable
                    :columns="columns"
                    :rows="log?.entries ?? []"
                    :row-key="(entry: Entry) => entry.id"
                    caption="Audit log entries"
                    :loading="navigating"
                    expandable
                    :expand-label="expandLabel"
                >
                    <template #cell-when="{ row }">
                        <time :datetime="row.createdAt">{{ formatDateTime(row.createdAt) }}</time>
                    </template>
                    <template #cell-action="{ row }">{{ row.actionLabel }}</template>
                    <template #cell-actor="{ row }">
                        <span class="text-fg">{{ actorName(row) }}</span>
                        <span v-if="row.actorRoleLabel" class="block text-fg-muted">{{ row.actorRoleLabel }}</span>
                    </template>
                    <template #cell-subject="{ row }">
                        <span :class="row.subjectName ? 'text-fg' : 'text-fg-muted'">{{ subjectName(row) }}</span>
                    </template>
                    <template #detail="{ row }">
                        <div class="flex flex-col gap-4">
                            <AdminDiffViewer :before="row.before" :after="row.after" />
                            <dl class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[max-content_1fr]">
                                <template v-for="(value, key) in row.context" :key="key">
                                    <dt class="font-medium text-fg-secondary">{{ key }}</dt>
                                    <dd class="break-all text-fg">{{ typeof value === 'string' ? value : JSON.stringify(value) }}</dd>
                                </template>
                                <template v-if="row.requestId">
                                    <dt class="font-medium text-fg-secondary">Request id</dt>
                                    <dd class="break-all text-fg">{{ row.requestId }}</dd>
                                </template>
                                <template v-if="row.userAgent">
                                    <dt class="font-medium text-fg-secondary">Browser</dt>
                                    <dd class="break-all text-fg">{{ row.userAgent }}</dd>
                                </template>
                            </dl>
                        </div>
                    </template>
                    <template #empty>
                        {{ hasFilters ? 'No entries match these filters.' : 'Nothing has been logged yet.' }}
                    </template>
                </AdminTable>

                <nav v-if="log && (log.newerCursor || log.olderCursor)" aria-label="Audit log pages" class="flex flex-wrap gap-2">
                    <UiButton v-if="log.newerCursor" variant="secondary" size="sm" :disabled="navigating" @click="goTo(log.newerCursor)"
                        >Newer</UiButton
                    >
                    <UiButton v-if="log.olderCursor" variant="secondary" size="sm" :disabled="navigating" @click="goTo(log.olderCursor)"
                        >Older</UiButton
                    >
                </nav>
            </div>
        </Deferred>
    </div>
</template>
