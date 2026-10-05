<script setup lang="ts">
import AdminSanctionHistory from '@/Components/admin/AdminSanctionHistory.vue';
import AdminTable, { type AdminColumn } from '@/Components/admin/AdminTable.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import UiRadioGroup from '@/Components/ui/UiRadioGroup.vue';
import UiTextarea from '@/Components/ui/UiTextarea.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { index, show } from '@/routes/admin/disputes';
import { store as decisionStore } from '@/routes/admin/disputes/decision';
import { show as adminUser } from '@/routes/admin/users';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AdminLayout });

type Props = App.Http.Data.Admin.AdminDisputeShowPageData;
type Review = App.Domain.PlayerAccounts.Data.DisputeReviewData;
type Party = App.Domain.PlayerAccounts.Data.DisputePartyData;
type Decision = App.Domain.PlayerAccounts.Enums.DisputeDecision;

// specs/13 §5 step 4 and specs/12 §4: everything the decision needs on one screen. Evidence links
// are signed for a few minutes; reload the page for fresh ones.
const props = defineProps<{
    dispute: Props['dispute'];
    claimantSanctions: Props['claimantSanctions'];
    holderSanctions: Props['holderSanctions'];
}>();

const d = computed<Review>(() => props.dispute);

const sides = computed(() =>
    [
        { key: 'claimant' as const, title: 'Claimant', party: d.value.claimant, sanctions: props.claimantSanctions },
        { key: 'holder' as const, title: 'Holder', party: d.value.holder, sanctions: props.holderSanctions },
    ].filter(
        (side): side is { key: 'claimant' | 'holder'; title: string; party: Party; sanctions: Props['claimantSanctions'] } => side.party !== null,
    ),
);

const evidenceOf = (party: 'claimant' | 'holder') => d.value.evidence.filter((entry) => entry.party === party);

const available = computed(() =>
    d.value.decisions.filter((option) => option.available).map((option) => ({ value: option.value, label: option.label })),
);
const unavailable = computed(() => d.value.decisions.filter((option) => !option.available && d.value.blockedReason === null));

const form = useForm({ decision: '' as Decision | '', note: '' });
const confirming = ref(false);

// The decisions that move or keep the tag say who ends up with it before they are sent.
const CONFIRMED: Decision[] = ['transfer', 'suspend', 'deny'];
const outcome = computed(() => {
    const holder = d.value.holder?.username ?? 'nobody';
    switch (form.decision) {
        case 'transfer':
            return `${d.value.tag} becomes verified for ${d.value.claimant.username}. ${holder} keeps an unverified row.`;
        case 'suspend':
            return `${d.value.tag} is suspended: neither ${d.value.claimant.username} nor ${holder} gets it, and nobody can verify it until staff release it.`;
        case 'deny':
            return `${holder} keeps ${d.value.tag}. ${d.value.claimant.username}'s claim is denied.`;
        default:
            return '';
    }
});

function send() {
    form.post(decisionStore(d.value.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            form.reset();
        },
        onError: () => {
            confirming.value = false;
        },
    });
}

function submit() {
    if (form.decision !== '' && CONFIRMED.includes(form.decision)) {
        confirming.value = true;
        return;
    }
    send();
}

const STATUS_TONE: Record<string, PillTone> = {
    awaiting_admin: 'warning',
    open: 'info',
    awaiting_holder: 'info',
    awaiting_claimant: 'info',
    resolved_transfer: 'success',
    resolved_suspended: 'danger',
};

const claimColumns: AdminColumn[] = [
    { key: 'at', label: 'When', class: 'w-56 whitespace-nowrap' },
    { key: 'username', label: 'User', class: 'min-w-40' },
    { key: 'method', label: 'How', class: 'w-40' },
    { key: 'result', label: 'Result', class: 'min-w-48' },
];

const snapshotColumns: AdminColumn[] = [
    { key: 'at', label: 'Captured', class: 'w-56 whitespace-nowrap' },
    { key: 'th', label: 'Town Hall', class: 'w-28 tabular-nums' },
    { key: 'clan', label: 'Clan', class: 'min-w-32' },
    { key: 'trophies', label: 'Trophies', class: 'w-28 tabular-nums' },
];
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex flex-col gap-1">
                <h1 class="text-h2 font-semibold">
                    Dispute <span class="font-mono">{{ d.tag }}</span>
                </h1>
                <div class="flex flex-wrap items-center gap-2 text-sm text-fg-secondary">
                    <UiPill :label="d.statusLabel" :tone="STATUS_TONE[d.status] ?? 'neutral'" />
                    <span
                        >{{ d.accountName }}<template v-if="d.accountTownHall">, Town Hall {{ d.accountTownHall }}</template></span
                    >
                </div>
            </div>
            <UiButton variant="ghost" size="sm" :href="index().url">All disputes</UiButton>
        </div>

        <dl class="grid gap-x-6 gap-y-2 rounded-sm border border-line bg-surface p-4 text-sm sm:grid-cols-[max-content_1fr]">
            <dt class="font-medium text-fg-secondary">Opened</dt>
            <dd class="text-fg">
                <time :datetime="d.openedAt">{{ formatDateTime(d.openedAt) }}</time>
            </dd>
            <dt class="font-medium text-fg-secondary">Waiting since</dt>
            <dd class="text-fg">
                <time :datetime="d.waitingSince">{{ formatDateTime(d.waitingSince) }}</time>
            </dd>
            <template v-if="d.escalatedAt">
                <dt class="font-medium text-fg-secondary">Sent to admins</dt>
                <dd class="text-fg">
                    <time :datetime="d.escalatedAt">{{ formatDateTime(d.escalatedAt) }}</time
                    >, the holder did not answer in time
                </dd>
            </template>
            <dt class="font-medium text-fg-secondary">Assigned</dt>
            <dd class="text-fg">{{ d.assignedTo ?? 'Nobody yet' }}</dd>
            <template v-if="d.decidedAt">
                <dt class="font-medium text-fg-secondary">Closed</dt>
                <dd class="text-fg">
                    <time :datetime="d.decidedAt">{{ formatDateTime(d.decidedAt) }}</time
                    ><template v-if="d.decidedBy"> by {{ d.decidedBy }}</template>
                </dd>
            </template>
            <template v-if="d.decisionNote">
                <dt class="font-medium text-fg-secondary">Internal note</dt>
                <dd class="whitespace-pre-line text-fg">{{ d.decisionNote }}</dd>
            </template>
        </dl>

        <section aria-labelledby="parties-heading" class="flex flex-col gap-3">
            <h2 id="parties-heading" class="text-h3 font-semibold">Parties</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <section
                    v-for="side in sides"
                    :key="side.key"
                    :aria-label="side.title"
                    class="flex flex-col gap-3 rounded-sm border border-line bg-surface p-4 text-sm"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-fg-secondary">{{ side.title }}</span>
                        <Link :href="adminUser(side.party.ulid).url" class="font-semibold break-all text-fg underline-offset-2 hover:underline">{{
                            side.party.username
                        }}</Link>
                        <UiPill :label="side.party.statusLabel" :tone="side.party.statusTone as PillTone" />
                        <span v-if="side.party.deleted" class="text-fg-muted">Deleted</span>
                    </div>
                    <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1">
                        <dt class="text-fg-secondary">Role</dt>
                        <dd class="text-fg">{{ side.party.roleLabel }}</dd>
                        <dt class="text-fg-secondary">Joined</dt>
                        <dd class="text-fg">
                            <time :datetime="side.party.joinedAt">{{ formatDateTime(side.party.joinedAt) }}</time>
                        </dd>
                        <dt class="text-fg-secondary">Verified accounts</dt>
                        <dd class="text-fg tabular-nums">{{ side.party.verifiedAccounts }}</dd>
                    </dl>
                    <div class="flex flex-col gap-1">
                        <h3 class="font-semibold text-fg">Other disputes</h3>
                        <p v-if="side.party.priorDisputes.length === 0" class="text-fg-secondary">None.</p>
                        <ul v-else class="flex flex-col gap-1">
                            <li v-for="prior in side.party.priorDisputes" :key="prior.ulid" class="flex flex-wrap items-center gap-2">
                                <Link :href="show(prior.ulid).url" class="font-mono text-fg underline-offset-2 hover:underline">{{ prior.tag }}</Link>
                                <span class="text-fg-secondary">as {{ prior.side }}</span>
                                <UiPill :label="prior.statusLabel" :tone="STATUS_TONE[prior.status] ?? 'neutral'" />
                                <time class="text-fg-muted" :datetime="prior.openedAt">{{ formatDateTime(prior.openedAt) }}</time>
                            </li>
                        </ul>
                    </div>
                    <div class="flex flex-col gap-1">
                        <h3 class="font-semibold text-fg">Sanctions</h3>
                        <AdminSanctionHistory :sanctions="side.sanctions" />
                    </div>
                </section>
            </div>
        </section>

        <section aria-labelledby="evidence-heading" class="flex flex-col gap-3">
            <h2 id="evidence-heading" class="text-h3 font-semibold">Statements and evidence</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <section v-for="side in sides" :key="side.key" :aria-label="`${side.title}'s evidence`" class="flex flex-col gap-3">
                    <h3 class="text-sm font-semibold text-fg-secondary">{{ side.title }}</h3>
                    <p v-if="evidenceOf(side.key).length === 0" class="text-sm text-fg-secondary">Nothing submitted.</p>
                    <article
                        v-for="(entry, position) in evidenceOf(side.key)"
                        :key="`${side.key}-${position}`"
                        class="flex flex-col gap-2 rounded-sm border border-line bg-surface p-3 text-sm"
                    >
                        <p class="flex flex-wrap gap-2 text-fg-secondary">
                            <span v-if="entry.opening" class="font-semibold text-fg">Opening statement</span>
                            <time :datetime="entry.at">{{ formatDateTime(entry.at) }}</time>
                        </p>
                        <p v-if="entry.note" class="break-words whitespace-pre-line text-fg">{{ entry.note }}</p>
                        <ul v-if="entry.images.length > 0" class="flex flex-wrap gap-2">
                            <li v-for="image in entry.images" :key="image.ulid">
                                <a v-if="image.url" :href="image.url" target="_blank" rel="noopener noreferrer" class="block">
                                    <img
                                        :src="image.thumbUrl ?? image.url"
                                        alt="Evidence image"
                                        class="size-24 rounded-sm border border-line object-cover"
                                    />
                                </a>
                                <span
                                    v-else
                                    class="flex size-24 items-center justify-center rounded-sm border border-line p-2 text-center text-fg-muted"
                                    >Image not available</span
                                >
                            </li>
                        </ul>
                    </article>
                </section>
            </div>
        </section>

        <section aria-labelledby="decision-heading" class="flex flex-col gap-3">
            <h2 id="decision-heading" class="text-h3 font-semibold">Decision</h2>
            <UiAlert v-if="d.blockedReason" kind="info">{{ d.blockedReason }}</UiAlert>
            <form v-else class="flex flex-col gap-4 rounded-sm border border-line bg-surface p-4" novalidate @submit.prevent="submit">
                <UiAlert v-if="form.errors.decision" kind="danger">{{ form.errors.decision }}</UiAlert>
                <UiRadioGroup v-model="form.decision" legend="What happens to the account" :options="available" />
                <ul v-if="unavailable.length > 0" class="flex flex-col gap-1 text-sm text-fg-secondary">
                    <li v-for="option in unavailable" :key="option.value">
                        <span class="text-fg">{{ option.label }}</span
                        >: {{ option.unavailableReason }}
                    </li>
                </ul>
                <UiTextarea
                    v-model="form.note"
                    label="Internal note"
                    hint="Staff only. What you weighed and why. Without decisive evidence, the holder keeps the account."
                    :maxlength="d.noteMax"
                    counter
                    :rows="3"
                    required
                    :error="form.errors.note"
                />
                <div>
                    <UiButton type="submit" :disabled="form.decision === ''" :loading="form.processing && !confirming">Record the decision</UiButton>
                </div>
            </form>
        </section>

        <section aria-labelledby="claims-heading" class="flex flex-col gap-3">
            <h2 id="claims-heading" class="text-h3 font-semibold">Claim history</h2>
            <AdminTable
                :columns="claimColumns"
                :rows="d.claims"
                :row-key="(row: App.Domain.PlayerAccounts.Data.DisputeClaimData) => `${row.at}-${row.username}-${row.statusLabel}`"
                caption="Every attempt on this tag, newest first"
            >
                <template #cell-at="{ row }"
                    ><time :datetime="row.at">{{ formatDateTime(row.at) }}</time></template
                >
                <template #cell-username="{ row }"
                    ><span class="break-all">{{ row.username }}</span></template
                >
                <template #cell-method="{ row }">{{ row.methodLabel }}</template>
                <template #cell-result="{ row }"
                    >{{ row.statusLabel }}<template v-if="row.failureLabel">: {{ row.failureLabel }}</template></template
                >
                <template #empty>No attempts recorded.</template>
            </AdminTable>
        </section>

        <section aria-labelledby="snapshots-heading" class="flex flex-col gap-3">
            <h2 id="snapshots-heading" class="text-h3 font-semibold">Account history</h2>
            <p class="text-sm text-fg-secondary">Stored snapshots of the account, newest first. In-game names are not kept.</p>
            <AdminTable
                :columns="snapshotColumns"
                :rows="d.snapshots"
                :row-key="(row: App.Domain.PlayerAccounts.Data.DisputeSnapshotData) => row.capturedAt"
                caption="Snapshots of this tag"
            >
                <template #cell-at="{ row }"
                    ><time :datetime="row.capturedAt">{{ formatDateTime(row.capturedAt) }}</time></template
                >
                <template #cell-th="{ row }">
                    {{ row.townHallLevel ?? 'Unknown' }}<span v-if="row.changes.includes('th')" class="ml-2 text-fg-secondary">changed</span>
                </template>
                <template #cell-clan="{ row }">
                    <span class="font-mono">{{ row.clanTag ?? 'No clan' }}</span
                    ><span v-if="row.changes.includes('clan')" class="ml-2 text-fg-secondary">changed</span>
                </template>
                <template #cell-trophies="{ row }">{{ row.trophies ?? 'Unknown' }}</template>
                <template #empty>No snapshots yet.</template>
            </AdminTable>
        </section>

        <UiModal v-model:open="confirming" title="Record this decision?">
            <div class="flex flex-col gap-4">
                <p class="text-body text-fg">{{ outcome }}</p>
                <p class="text-sm text-fg-secondary">It cannot be undone here.</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <UiButton variant="ghost" :disabled="form.processing" @click="confirming = false">Cancel</UiButton>
                    <UiButton
                        :variant="form.decision === 'transfer' || form.decision === 'suspend' ? 'danger' : 'primary'"
                        :loading="form.processing"
                        @click="send"
                        >Record the decision</UiButton
                    >
                </div>
            </div>
        </UiModal>
    </div>
</template>
