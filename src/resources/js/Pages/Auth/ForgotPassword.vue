<script setup lang="ts">
import TurnstileWidget from '@/Components/auth/TurnstileWidget.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.ForgotPasswordPageData;

defineProps<{ status: Props['status']; turnstileSiteKey: Props['turnstileSiteKey'] }>();

const form = useForm({ email: '', turnstile_token: null as string | null });
const formEl = ref<HTMLFormElement | null>(null);
const turnstile = ref<InstanceType<typeof TurnstileWidget> | null>(null);

function submit() {
    form.post(email().url, {
        preserveScroll: true,
        onError: () => {
            turnstile.value?.reset();
            focusFirstError(formEl.value);
        },
        // A token works once, so every submit needs a fresh one.
        onSuccess: () => turnstile.value?.reset(),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <header class="flex flex-col gap-2">
            <h1 class="font-display text-h1">Reset your password</h1>
            <p class="text-body text-fg-secondary">Enter the email you signed up with. We send a link there to choose a new password.</p>
        </header>

        <UiAlert v-if="status" kind="success">{{ status }}</UiAlert>

        <UiCard class="p-5 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <UiInput v-model="form.email" label="Email" type="email" autocomplete="email" required autofocus :error="form.errors.email" />
                <TurnstileWidget ref="turnstile" v-model="form.turnstile_token" :site-key="turnstileSiteKey" :error="form.errors.turnstile_token" />
                <UiButton type="submit" block :loading="form.processing">Send the reset link</UiButton>
            </form>
        </UiCard>

        <p class="text-sm text-fg-secondary">
            Remembered it?
            <Link
                :href="login().url"
                class="font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                Back to sign in
            </Link>
        </p>
    </div>
</template>
