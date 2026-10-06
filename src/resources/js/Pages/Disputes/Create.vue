<script setup lang="ts">
import PlayerPreviewCard from '@/Components/accounts/PlayerPreviewCard.vue';
import DisputeEvidenceField from '@/Components/disputes/DisputeEvidenceField.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiTextarea from '@/Components/ui/UiTextarea.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import AppLayout from '@/Layouts/AppLayout.vue';
import { attach } from '@/routes/accounts';
import { store } from '@/routes/disputes';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

// Opening an ownership dispute from the attach flow's conflict card (specs/13 §4 B, §5 step 1).
// For someone who owns the game account but cannot get its token; the token path stays first.
type Props = App.Http.Data.Disputes.DisputeCreatePageData;
const props = defineProps<{
    tag: Props['tag'];
    player: Props['player'];
    refusal: Props['refusal'];
    evidenceUpload: Props['evidenceUpload'];
    evidenceMax: Props['evidenceMax'];
    textMax: Props['textMax'];
    responseDays: Props['responseDays'];
}>();

const form = useForm({ tag: props.tag, reason: '', evidence: [] as string[] });
const formEl = ref<HTMLFormElement | null>(null);
const uploading = ref(false);
const tokenPath = computed(() => attach({ query: { tag: props.tag } }).url);
// A bad upload id comes back on its own index (`evidence.1`).
const evidenceError = computed(() => form.errors.evidence ?? Object.entries(form.errors).find(([key]) => key.startsWith('evidence.'))?.[1]);

function submit() {
    if (uploading.value) return;
    form.post(store().url, { preserveScroll: true, onError: () => focusFirstError(formEl.value) });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-3">
            <h1 class="font-display text-h1">
                Dispute <span class="font-mono">{{ tag }}</span>
            </h1>
            <p class="text-body text-fg-secondary">
                For an account that is yours when you cannot get its API token, for example after losing the device it was on. If you can open the
                account in game, <Link :href="tokenPath" class="font-medium text-brand underline underline-offset-4">verify it with a token</Link>
                instead: that is instant.
            </p>
        </header>

        <UiAlert v-if="refusal" kind="warning" title="You cannot dispute this account right now">{{ refusal }}</UiAlert>

        <template v-else>
            <PlayerPreviewCard v-if="player" :player="player" />

            <UiCard class="flex flex-col gap-3 p-4 sm:p-6">
                <h2 class="text-h3 font-semibold">What happens next</h2>
                <ul class="flex list-disc flex-col gap-1 pl-5 text-body text-fg-secondary">
                    <li>The holder is told someone claims the account, not who, and has {{ responseDays }} days to answer.</li>
                    <li>A token from the game ends the dispute at once, from either side.</li>
                    <li>Otherwise the admins weigh both sides. Without clear evidence, the holder keeps the account.</li>
                    <li>Only the admins see what you send. Denied disputes count against you.</li>
                </ul>
            </UiCard>

            <UiCard class="p-4 sm:p-6">
                <form ref="formEl" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                    <UiTextarea
                        v-model="form.reason"
                        label="Why is this account yours?"
                        hint="Say how you lost access, and what only the owner would know: when you started, clans you were in, recent changes."
                        :maxlength="textMax"
                        counter
                        :rows="6"
                        required
                        :error="form.errors.reason ?? form.errors.tag"
                    />
                    <DisputeEvidenceField
                        v-model="form.evidence"
                        v-model:busy="uploading"
                        label="Images (optional)"
                        hint="Screenshots from inside the game, or an email from Supercell support. Never an ID card or passport."
                        :max="evidenceMax"
                        :upload="evidenceUpload"
                        :error="evidenceError"
                    />
                    <div class="flex flex-col gap-2">
                        <UiButton type="submit" block :loading="form.processing" :disabled="uploading">Open the dispute</UiButton>
                        <p v-if="uploading" class="text-sm text-fg-secondary">Waiting for your images to finish uploading.</p>
                    </div>
                </form>
            </UiCard>
        </template>
    </div>
</template>
