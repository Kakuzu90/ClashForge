<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { request, update } from '@/routes/password';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.ResetPasswordPageData;

const props = defineProps<{ token: Props['token']; email: Props['email'] }>();

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const formEl = ref<HTMLFormElement | null>(null);

function submit() {
    form.post(update().url, {
        onError: () => focusFirstError(formEl.value),
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <header class="flex flex-col gap-2">
            <h1 class="font-display text-h1">Choose a new password</h1>
            <p class="text-body text-fg-secondary">At least 10 characters. Saving it signs you out on every other device.</p>
        </header>

        <UiCard class="p-5 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <UiInput v-model="form.email" label="Email" type="email" autocomplete="username" readonly :error="form.errors.email" />
                <UiInput
                    v-model="form.password"
                    label="New password"
                    type="password"
                    autocomplete="new-password"
                    required
                    autofocus
                    :error="form.errors.password"
                />
                <UiInput
                    v-model="form.password_confirmation"
                    label="Repeat the new password"
                    type="password"
                    autocomplete="new-password"
                    required
                    :error="form.errors.password_confirmation"
                />
                <UiButton type="submit" block :loading="form.processing">Save the new password</UiButton>
            </form>
        </UiCard>

        <p v-if="form.errors.email" class="text-sm text-fg-secondary">
            <Link
                :href="request().url"
                class="font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                Ask for a new reset link
            </Link>
        </p>
    </div>
</template>
