<script setup lang="ts">
import AdminActionPanel from '@/Components/admin/AdminActionPanel.vue';
import AdminAuditTrailList from '@/Components/admin/AdminAuditTrailList.vue';
import AdminSanctionHistory from '@/Components/admin/AdminSanctionHistory.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { audit } from '@/routes/admin';
import { index } from '@/routes/admin/users';
import { computed } from 'vue';

defineOptions({ layout: AdminLayout });

type Props = App.Http.Data.Admin.AdminUserShowPageData;

// specs/12 §4 author context and FR-ADMIN-6: answer support questions from data; FR-ADMIN-3:
// suspend, ban and lift from here, with the account's sanction history.
const props = defineProps<{
    user: Props['user'];
    displayName: Props['displayName'];
    avatarUrl: Props['avatarUrl'];
    auditTrail: Props['auditTrail'];
    moreAuditEntries: Props['moreAuditEntries'];
    sanctions: Props['sanctions'];
    sanctionHistory: Props['sanctionHistory'];
    sanctionForm: Props['sanctionForm'];
}>();

const sanctioned = computed(() => props.user.statusReason !== null || props.user.statusEndsAt !== null);
const fullLogUrl = computed(() => audit({ query: { target: props.user.username } }).url);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                <UiAvatar :name="displayName ?? user.username" :src="avatarUrl" :size="48" />
                <div class="flex min-w-0 flex-col">
                    <h1 class="text-h2 font-semibold break-all">{{ user.username }}</h1>
                    <p v-if="displayName" class="text-sm text-fg-secondary">{{ displayName }}</p>
                </div>
            </div>
            <UiButton variant="ghost" size="sm" :href="index().url">All users</UiButton>
        </div>

        <UiAlert v-if="user.deletedAt" kind="warning" title="This account is deleted">
            Deleted on <time :datetime="user.deletedAt">{{ formatDateTime(user.deletedAt) }}</time
            >.
        </UiAlert>

        <section aria-labelledby="account-heading" class="flex flex-col gap-3">
            <h2 id="account-heading" class="text-h3 font-semibold">Account</h2>
            <dl class="grid gap-x-6 gap-y-2 rounded-sm border border-line bg-surface p-4 text-sm sm:grid-cols-[max-content_1fr]">
                <dt class="font-medium text-fg-secondary">Status</dt>
                <dd class="flex flex-col items-start gap-1">
                    <UiPill :label="user.statusLabel" :tone="user.statusTone as PillTone" />
                    <span v-if="sanctioned && user.statusReason" class="text-fg">Reason shown to them: {{ user.statusReason }}</span>
                    <span v-if="sanctioned" class="text-fg-secondary">
                        <template v-if="user.statusEndsAt"
                            >Ends <time :datetime="user.statusEndsAt">{{ formatDateTime(user.statusEndsAt) }}</time></template
                        >
                        <template v-else>No end date</template>
                    </span>
                </dd>
                <dt class="font-medium text-fg-secondary">Role</dt>
                <dd class="text-fg">{{ user.roleLabel }}</dd>
                <dt class="font-medium text-fg-secondary">Email</dt>
                <dd class="break-all text-fg">
                    {{ user.email }}
                    <span class="block text-fg-secondary">
                        <template v-if="user.emailVerifiedAt"
                            >Verified <time :datetime="user.emailVerifiedAt">{{ formatDateTime(user.emailVerifiedAt) }}</time></template
                        >
                        <template v-else>Not verified</template>
                    </span>
                </dd>
                <dt class="font-medium text-fg-secondary">Joined</dt>
                <dd class="text-fg">
                    <time :datetime="user.joinedAt">{{ formatDateTime(user.joinedAt) }}</time>
                </dd>
                <dt class="font-medium text-fg-secondary">Last sign-in</dt>
                <dd class="text-fg">
                    <time v-if="user.lastSignInAt" :datetime="user.lastSignInAt">{{ formatDateTime(user.lastSignInAt) }}</time>
                    <span v-else class="text-fg-muted">Never</span>
                </dd>
                <dt class="font-medium text-fg-secondary">Signed in now</dt>
                <dd class="text-fg">{{ user.activeSessions === 1 ? '1 browser' : `${user.activeSessions} browsers` }}</dd>
                <dt class="font-medium text-fg-secondary">Account id</dt>
                <dd class="break-all text-fg">{{ user.ulid }}</dd>
            </dl>
        </section>

        <section aria-labelledby="standing-heading" class="flex flex-col gap-3">
            <h2 id="standing-heading" class="text-h3 font-semibold">Sanctions</h2>
            <AdminActionPanel :ulid="user.ulid" :username="user.username" :abilities="sanctions" :options="sanctionForm" />
            <AdminSanctionHistory :sanctions="sanctionHistory" />
        </section>

        <section aria-labelledby="trail-heading" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="trail-heading" class="text-h3 font-semibold">Audit trail</h2>
                <UiButton v-if="moreAuditEntries" variant="ghost" size="sm" :href="fullLogUrl">See all in the audit log</UiButton>
            </div>
            <AdminAuditTrailList :entries="auditTrail" />
        </section>
    </div>
</template>
