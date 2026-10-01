<script setup lang="ts">
import AdminFilterBar from '@/Components/admin/AdminFilterBar.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import UiSelect, { type SelectOption } from '@/Components/ui/UiSelect.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { useNavigating } from '@/Composables/useNavigating';
import { useVisitError } from '@/Composables/useVisitError';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { index, show } from '@/routes/admin/users';
import { Deferred, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

defineOptions({ layout: AdminLayout });

type Page = App.Http.Data.Admin.AdminUserIndexPageData;
type Row = App.Domain.Auth.Data.AdminUserRowData;
type Filters = Page['filters'];

// FR-ADMIN-2 (users, read): every account, newest first, found by username prefix or email.
const props = defineProps<{
    filters: Filters;
    roles: Page['roles'];
    statuses: Page['statuses'];
    users?: App.Http.Data.Admin.AdminUserListData;
}>();

const page = usePage();
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);

const ANY = '';
const fields = reactive({ search: '', role: ANY, status: ANY });

function fill(filters: Filters) {
    fields.search = filters.search ?? '';
    fields.role = filters.role ?? ANY;
    fields.status = filters.status ?? ANY;
}
fill(props.filters);
watch(() => props.filters, fill);

const roleOptions = computed<SelectOption[]>(() => [{ value: ANY, label: 'Any role' }, ...props.roles]);
const statusOptions = computed<SelectOption[]>(() => [{ value: ANY, label: 'Any status' }, ...props.statuses]);
const hasFilters = computed(() => Object.values(props.filters).some((value) => value !== null));

const navigating = useNavigating((url) => url.pathname === index().url);
const visitError = useVisitError();

function visit(query: Record<string, string>) {
    router.get(index().url, query, { preserveState: true, preserveScroll: true });
}

function apply() {
    visit(Object.fromEntries(Object.entries(fields).filter(([, value]) => value.trim() !== '')));
}

function clear() {
    fill({ search: null, role: null, status: null });
    visit({});
}

function goTo(cursor: string) {
    const current = Object.fromEntries(Object.entries(props.filters).filter((entry): entry is [string, string] => entry[1] !== null));
    visit({ ...current, cursor });
}

const columns: AdminColumn[] = [
    { key: 'username', label: 'Username', class: 'min-w-40' },
    { key: 'email', label: 'Email', class: 'min-w-56' },
    { key: 'role', label: 'Role', class: 'w-32' },
    { key: 'status', label: 'Status', class: 'w-40' },
    { key: 'joined', label: 'Joined', class: 'w-56 whitespace-nowrap' },
    { key: 'lastSignIn', label: 'Last sign-in', class: 'w-56 whitespace-nowrap' },
];
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-h2 font-semibold">Users</h1>
            <p class="text-sm text-fg-secondary">Every account, newest first, including banned and deleted ones.</p>
        </div>

        <AdminFilterBar label="Find accounts" :busy="navigating" :can-clear="hasFilters" @apply="apply" @clear="clear">
            <UiInput
                v-model="fields.search"
                label="Search"
                type="search"
                hint="Start of a username, or a full email"
                autocomplete="off"
                :maxlength="254"
                :error="errors.search"
            />
            <UiSelect v-model="fields.role" label="Role" :options="roleOptions" :error="errors.role" />
            <UiSelect v-model="fields.status" label="Status" :options="statusOptions" :error="errors.status" />
        </AdminFilterBar>

        <UiAlert v-if="visitError?.throttled" kind="warning" title="Too many searches in a minute">
            <p>Wait a moment, then try again.</p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>
        <UiAlert v-else-if="visitError" kind="danger" title="The user list didn't load">
            <p>
                Try again in a moment.<template v-if="visitError.requestId">
                    If it keeps failing, quote request id <span class="font-semibold text-fg">{{ visitError.requestId }}</span
                    >.</template
                >
            </p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>

        <Deferred data="users">
            <template #fallback>
                <AdminTable v-if="!visitError" :columns="columns" :rows="[]" :row-key="() => 0" caption="Accounts" loading />
            </template>

            <div class="flex flex-col gap-3">
                <AdminTable
                    :columns="columns"
                    :rows="users?.entries ?? []"
                    :row-key="(row: Row) => row.ulid"
                    caption="Accounts"
                    :loading="navigating"
                >
                    <template #cell-username="{ row }">
                        <Link :href="show(row.ulid).url" class="font-semibold text-fg underline-offset-2 hover:underline">{{ row.username }}</Link>
                        <span v-if="row.deleted" class="block text-fg-muted">Deleted</span>
                    </template>
                    <template #cell-email="{ row }">
                        <span class="break-all">{{ row.email }}</span>
                        <span v-if="!row.emailVerified" class="block text-fg-muted">Not verified</span>
                    </template>
                    <template #cell-role="{ row }">{{ row.roleLabel }}</template>
                    <template #cell-status="{ row }">
                        <UiPill :label="row.statusLabel" :tone="row.statusTone as PillTone" />
                    </template>
                    <template #cell-joined="{ row }">
                        <time :datetime="row.joinedAt">{{ formatDateTime(row.joinedAt) }}</time>
                    </template>
                    <template #cell-lastSignIn="{ row }">
                        <time v-if="row.lastSignInAt" :datetime="row.lastSignInAt">{{ formatDateTime(row.lastSignInAt) }}</time>
                        <span v-else class="text-fg-muted">Never</span>
                    </template>
                    <template #empty>No accounts match.</template>
                </AdminTable>

                <nav v-if="users && (users.newerCursor || users.olderCursor)" aria-label="User list pages" class="flex flex-wrap gap-2">
                    <UiButton v-if="users.newerCursor" variant="secondary" size="sm" :disabled="navigating" @click="goTo(users.newerCursor)"
                        >Newer</UiButton
                    >
                    <UiButton v-if="users.olderCursor" variant="secondary" size="sm" :disabled="navigating" @click="goTo(users.olderCursor)"
                        >Older</UiButton
                    >
                </nav>
            </div>
        </Deferred>
    </div>
</template>
