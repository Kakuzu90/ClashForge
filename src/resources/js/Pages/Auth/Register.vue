<script setup lang="ts">
import TurnstileWidget from '@/Components/auth/TurnstileWidget.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.RegisterPageData;

// FR-AUTH-1/11. `website` is a field people never see (a bot trap) and `started` the encrypted
// time the form was shown (specs/11 "Spam and fake accounts").
const props = defineProps<{
    usernameMin: Props['usernameMin'];
    usernameMax: Props['usernameMax'];
    passwordMin: Props['passwordMin'];
    turnstileSiteKey: Props['turnstileSiteKey'];
    formStarted: Props['formStarted'];
}>();

const form = useForm({
    email: '',
    username: '',
    password: '',
    password_confirmation: '',
    turnstile_token: null as string | null,
    website: '',
    started: props.formStarted,
});
const formEl = ref<HTMLFormElement | null>(null);
const turnstile = ref<InstanceType<typeof TurnstileWidget> | null>(null);

function submit() {
    form.post(store().url, {
        onError: () => {
            turnstile.value?.reset();
            focusFirstError(formEl.value);
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <header class="flex flex-col gap-2">
            <h1 class="font-display text-h1">Create your account</h1>
            <p class="text-body text-fg-secondary">Share bases, show off your verified accounts and find a clan.</p>
        </header>

        <UiCard class="p-5 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <UiInput v-model="form.email" label="Email" type="email" autocomplete="email" required autofocus :error="form.errors.email" />
                <UiInput
                    v-model="form.username"
                    label="Username"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    required
                    :maxlength="usernameMax"
                    :hint="`${usernameMin} to ${usernameMax} lowercase letters, numbers or underscores. This is your public name.`"
                    :error="form.errors.username"
                />
                <UiInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    autocomplete="new-password"
                    required
                    :hint="`At least ${passwordMin} characters.`"
                    :error="form.errors.password"
                />
                <UiInput
                    v-model="form.password_confirmation"
                    label="Repeat the password"
                    type="password"
                    autocomplete="new-password"
                    required
                    :error="form.errors.password_confirmation"
                />

                <!-- Bot trap: hidden from people and assistive tech, left empty by anyone who can see the form. -->
                <div class="sr-only" aria-hidden="true">
                    <label for="register-website">Leave this field empty</label>
                    <input id="register-website" v-model="form.website" name="website" type="text" tabindex="-1" autocomplete="off" />
                </div>

                <TurnstileWidget ref="turnstile" v-model="form.turnstile_token" :site-key="turnstileSiteKey" :error="form.errors.turnstile_token" />

                <UiButton type="submit" block :loading="form.processing">Create account</UiButton>
            </form>
        </UiCard>

        <p class="text-sm text-fg-secondary">
            Already have an account?
            <Link
                :href="login().url"
                class="font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                Sign in
            </Link>
        </p>
    </div>
</template>
