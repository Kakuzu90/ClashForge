<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { destroy, resend, update } from '@/routes/settings/security/email';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

// FR-AUTH-8: the new address gets a link and changes nothing until it is confirmed. Both
// addresses arrive masked. The server answers the same for every address, so this never says
// whether one is in use.
defineProps<{
    email: string;
    pendingEmail: string | null;
    linkMinutes: number;
}>();

// Sending and sending again both take the current password from this form; a breach of the
// `email-change` limit comes back as an `email` error.
const form = useForm({ email: '', current_password: '' });
const cancelForm = useForm({});
const resent = ref(false);
const formEl = ref<HTMLFormElement | null>(null);

function failed() {
    form.reset('current_password');
    focusFirstError(formEl.value);
}

function submit() {
    resent.value = false;
    form.put(update().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: failed,
    });
}

function sendAgain() {
    resent.value = false;
    form.post(resend().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('current_password');
            resent.value = true;
        },
        onError: failed,
    });
}

function cancel() {
    cancelForm.delete(destroy().url, { preserveScroll: true });
}
</script>

<template>
    <section aria-labelledby="email-heading" class="flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h3 id="email-heading" class="text-h3 text-fg">Email address</h3>
            <p class="text-sm text-fg-secondary">
                Your email is <span class="font-semibold break-all text-fg">{{ email }}</span
                >. A new address works once you confirm it from that inbox, and every other device is signed out.
            </p>
        </div>

        <div v-if="pendingEmail" class="flex flex-col gap-3 rounded-lg border border-line p-4" data-test="pending-email">
            <p class="text-body text-fg">
                Waiting for you to confirm <span class="font-semibold break-all">{{ pendingEmail }}</span
                >.
            </p>
            <p class="text-sm text-fg-secondary">
                The link expires {{ linkMinutes }} minutes after it was sent. Until then your email stays the same.
                To send it again, enter your current password below.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <UiButton variant="secondary" size="sm" :disabled="form.processing" @click="sendAgain">Send the link again</UiButton>
                <UiButton variant="ghost" size="sm" :loading="cancelForm.processing" @click="cancel">Cancel the change</UiButton>
                <span v-if="resent" role="status" class="text-sm text-fg-secondary">Sent.</span>
            </div>
        </div>

        <form ref="formEl" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
            <UiInput
                v-model="form.email"
                :label="pendingEmail ? 'Use a different new email' : 'New email'"
                type="email"
                autocomplete="email"
                required
                :error="form.errors.email"
            />
            <UiInput
                v-model="form.current_password"
                label="Current password"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.current_password"
            />
            <div class="flex items-center gap-3">
                <UiButton type="submit" :loading="form.processing">Send confirmation link</UiButton>
            </div>
        </form>
    </section>
</template>
