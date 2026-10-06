<script setup lang="ts">
import DisputeEvidenceField from '@/Components/disputes/DisputeEvidenceField.vue';
import { disputeMessage, outcomeLink, statusTone } from '@/Components/disputes/disputeView';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiTextarea from '@/Components/ui/UiTextarea.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import AppLayout from '@/Layouts/AppLayout.vue';
import { attach, show as accountShow, verify } from '@/routes/accounts';
import { release, respond, withdraw } from '@/routes/disputes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

// One party's view of their ownership dispute (specs/13 §5, P2-16): where it stands, whose turn it
// is, and the actions the server allows now. Only the viewer's own submissions are shown; the other
// party, their statements and their evidence never reach this page (owner decision 2026-10-06).
type Props = App.Http.Data.Disputes.DisputeShowPageData;
const props = defineProps<{ dispute: Props['dispute']; evidenceUpload: Props['evidenceUpload']; textMax: Props['textMax'] }>();

const d = computed(() => props.dispute);
const message = computed(() => disputeMessage(d.value));
const link = computed(() => outcomeLink(d.value));
const active = computed(() => d.value.outcome === null);

const answer = useForm({ statement: '', evidence: [] as string[] });
const answerEl = ref<HTMLFormElement | null>(null);
const evidenceField = ref<InstanceType<typeof DisputeEvidenceField> | null>(null);
const uploading = ref(false);
const evidenceError = computed(() => answer.errors.evidence ?? Object.entries(answer.errors).find(([key]) => key.startsWith('evidence.'))?.[1]);

function sendAnswer() {
    if (uploading.value) return;
    answer.post(respond(d.value.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            answer.reset();
            evidenceField.value?.clear();
        },
        onError: () => focusFirstError(answerEl.value),
    });
}

const releasing = ref(false);
const releaseForm = useForm({ current_password: '' });
const releaseEl = ref<HTMLFormElement | null>(null);
function giveUp() {
    releaseForm.post(release(d.value.ulid).url, {
        preserveScroll: true,
        onSuccess: () => (releasing.value = false),
        onError: () => focusFirstError(releaseEl.value),
        onFinish: () => releaseForm.reset('current_password'),
    });
}

const withdrawing = ref(false);
const withdrawForm = useForm({});
function withdrawClaim() {
    withdrawForm.post(withdraw(d.value.ulid).url, { preserveScroll: true, onFinish: () => (withdrawing.value = false) });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-2">
            <h1 class="font-display text-h1">Ownership dispute</h1>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-body">{{ d.tag }}</span>
                <UiPill :label="d.statusLabel" :tone="statusTone(d)" />
            </div>
            <p class="text-sm text-fg-secondary">
                {{ d.role === 'claimant' ? 'You claim this account.' : 'You hold this account.' }}
                Opened {{ formatDateTime(d.openedAt) }}<template v-if="d.closedAt">, closed {{ formatDateTime(d.closedAt) }}</template
                >.
            </p>
        </header>

        <UiAlert :kind="message.kind" :title="message.title">
            {{ message.body }}
            <div v-if="link" class="mt-3">
                <UiButton v-if="link === 'account' && d.accountUlid" :href="accountShow(d.accountUlid).url" size="sm">View your account</UiButton>
                <UiButton v-else-if="link === 'attach'" :href="attach({ query: { tag: d.tag } }).url" size="sm">Verify with a token</UiButton>
            </div>
        </UiAlert>

        <UiCard v-if="active && d.canVerify && d.accountUlid" class="flex flex-col gap-3 p-4 sm:p-6">
            <h2 class="text-h3 font-semibold">End it with a token</h2>
            <p class="text-body text-fg-secondary">A new API token from the game proves the account is yours and closes the dispute at once.</p>
            <UiButton class="self-start" :href="verify(d.accountUlid).url">Verify with a token</UiButton>
        </UiCard>

        <UiCard v-if="d.canRespond" class="p-4 sm:p-6">
            <form ref="answerEl" class="flex flex-col gap-5" novalidate @submit.prevent="sendAnswer">
                <h2 class="text-h3 font-semibold">Answer for the admins</h2>
                <UiTextarea
                    v-model="answer.statement"
                    label="Your answer"
                    hint="Only the admins read it. Say what shows the account is yours."
                    :maxlength="textMax"
                    counter
                    :rows="5"
                    required
                    :error="answer.errors.statement"
                />
                <DisputeEvidenceField
                    v-if="d.evidenceLeft > 0"
                    ref="evidenceField"
                    v-model="answer.evidence"
                    v-model:busy="uploading"
                    label="Images (optional)"
                    hint="Screenshots from inside the game, or an email from Supercell support. Never an ID card or passport."
                    :max="d.evidenceLeft"
                    :upload="evidenceUpload"
                    :error="evidenceError"
                />
                <div class="flex flex-col gap-2">
                    <UiButton type="submit" class="self-start" :loading="answer.processing" :disabled="uploading">Send to the admins</UiButton>
                    <p v-if="uploading" class="text-sm text-fg-secondary">Waiting for your images to finish uploading.</p>
                </div>
            </form>
        </UiCard>

        <div v-if="d.canRelease || d.canWithdraw" class="flex flex-col gap-2 border-t border-line pt-4">
            <template v-if="d.canRelease">
                <p class="text-sm text-fg-secondary">Not yours after all? You can give it up, and it moves to the other player.</p>
                <UiButton class="self-start" variant="ghost" size="sm" @click="releasing = true">Give the account up</UiButton>
            </template>
            <template v-if="d.canWithdraw">
                <p class="text-sm text-fg-secondary">Changed your mind? You can withdraw while the holder has not answered.</p>
                <UiButton class="self-start" variant="ghost" size="sm" @click="withdrawing = true">Withdraw my claim</UiButton>
            </template>
        </div>

        <section aria-labelledby="sent-heading" class="flex flex-col gap-3">
            <h2 id="sent-heading" class="text-h3 font-semibold">What you sent</h2>
            <p class="text-sm text-fg-secondary">Only the admins see this. The other player never does, and you never see theirs.</p>
            <p v-if="d.submissions.length === 0" class="text-sm text-fg-secondary">Nothing yet.</p>
            <ol v-else class="flex flex-col gap-3">
                <li
                    v-for="(entry, position) in d.submissions"
                    :key="position"
                    class="flex flex-col gap-2 rounded-sm border border-line bg-surface p-3 text-sm"
                >
                    <p class="text-fg-muted">{{ entry.opening ? 'Your claim' : 'Your answer' }}, {{ formatDateTime(entry.at) }}</p>
                    <p v-if="entry.note" class="whitespace-pre-line text-fg">{{ entry.note }}</p>
                    <ul v-if="entry.images.length" class="flex flex-wrap gap-2" aria-label="Images">
                        <li v-for="image in entry.images" :key="image.ulid">
                            <img
                                v-if="image.thumbUrl"
                                :src="image.thumbUrl"
                                alt="An image you sent"
                                class="size-20 rounded-sm object-cover"
                                width="80"
                                height="80"
                            />
                            <span
                                v-else
                                class="flex size-20 items-center justify-center rounded-sm border border-line p-1 text-center text-xs text-fg-muted"
                            >
                                Being prepared
                            </span>
                        </li>
                    </ul>
                    <p v-if="entry.removed > 0" class="text-sm text-fg-secondary">
                        {{ entry.removed === 1 ? 'An image was' : `${entry.removed} images were` }} removed by staff because
                        {{ entry.removed === 1 ? 'it' : 'they' }} showed an identity document.
                    </p>
                </li>
            </ol>
        </section>

        <UiModal v-if="d.canRelease" v-model:open="releasing" title="Give the account up?">
            <form ref="releaseEl" class="flex flex-col gap-4" novalidate @submit.prevent="giveUp">
                <p class="text-body text-fg-secondary">
                    {{ d.tag }} leaves your Clash Commons account and is verified on the other player's at once. You can only get it back with an
                    in-game API token.
                </p>
                <UiInput
                    v-model="releaseForm.current_password"
                    label="Current password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="releaseForm.errors.current_password"
                    :disabled="releaseForm.processing"
                />
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UiButton variant="secondary" :disabled="releaseForm.processing" @click="releasing = false">Keep it</UiButton>
                    <UiButton type="submit" variant="danger" :loading="releaseForm.processing">Give it up</UiButton>
                </div>
            </form>
        </UiModal>

        <UiModal v-if="d.canWithdraw" v-model:open="withdrawing" title="Withdraw your claim?">
            <div class="flex flex-col gap-4">
                <p class="text-body text-fg-secondary">
                    The dispute closes and the holder is told. You cannot dispute {{ d.tag }} again for a while.
                    <template v-if="d.withdrawCountsTowardBar">Withdrawing this soon after opening also counts like a denied dispute.</template>
                </p>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UiButton variant="secondary" :disabled="withdrawForm.processing" @click="withdrawing = false">Keep my claim</UiButton>
                    <UiButton variant="danger" :loading="withdrawForm.processing" @click="withdrawClaim">Withdraw</UiButton>
                </div>
            </div>
        </UiModal>
    </div>
</template>
