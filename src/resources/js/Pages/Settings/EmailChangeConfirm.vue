<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { edit } from '@/routes/settings/security';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Settings.EmailChangeConfirmPageData;

// Where an email-change link lands (FR-AUTH-8). Opening it names the account and the new
// address; the button confirms, so a mail scanner that opens the link changes nothing.
const props = defineProps<{
    outcome: Props['outcome'];
    message: Props['message'];
    username: Props['username'];
    newEmail: Props['newEmail'];
    confirmUrl: Props['confirmUrl'];
}>();

const pending = computed(() => props.outcome === 'pending' && props.confirmUrl !== null);
const ok = computed(() => props.outcome === 'changed' || props.outcome === 'already_changed');
const confirming = ref(false);

function confirm() {
    if (props.confirmUrl) {
        router.post(props.confirmUrl, {}, { onStart: () => (confirming.value = true), onFinish: () => (confirming.value = false) });
    }
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <template v-if="pending">
            <h1 class="font-display text-h1">Confirm your new email</h1>
            <UiCard class="flex flex-col gap-4 p-5 sm:p-6">
                <p class="text-body">
                    This changes the email for <span class="font-semibold break-all">{{ username }}</span> to
                    <span class="font-semibold break-all">{{ newEmail }}</span>.
                </p>
                <p class="text-sm text-fg-secondary">Every other device will be signed out. Did not ask for this? Close this page and nothing changes.</p>
                <UiButton block :loading="confirming" @click="confirm">Use this email</UiButton>
            </UiCard>
        </template>

        <template v-else>
            <h1 class="font-display text-h1">{{ ok ? 'Email changed' : 'Email not changed' }}</h1>

            <UiAlert :kind="ok ? 'success' : 'danger'">{{ message }}</UiAlert>

            <div class="flex flex-wrap gap-3">
                <UiButton :href="edit().url">Security settings</UiButton>
            </div>
        </template>
    </div>
</template>
