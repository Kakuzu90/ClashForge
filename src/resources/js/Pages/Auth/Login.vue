<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiCheckbox from '@/Components/ui/UiCheckbox.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.LoginPageData;

defineProps<{ status: Props['status'] }>();

const form = useForm({ email: '', password: '', remember: false });
const formEl = ref<HTMLFormElement | null>(null);

function submit() {
    form.post(store().url, {
        onError: () => focusFirstError(formEl.value),
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <h1 class="font-display text-h1">Sign in</h1>

        <UiAlert v-if="status" kind="success">{{ status }}</UiAlert>

        <UiCard class="p-5 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <UiInput v-model="form.email" label="Email" type="email" autocomplete="email" required :error="form.errors.email" />
                <UiInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="form.errors.password"
                />
                <div class="flex flex-wrap items-center justify-between gap-x-4">
                    <UiCheckbox v-model="form.remember" label="Keep me signed in" />
                    <Link
                        :href="request().url"
                        class="inline-flex min-h-11 items-center text-sm font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        Forgot your password?
                    </Link>
                </div>
                <UiButton type="submit" block :loading="form.processing">Sign in</UiButton>
            </form>
        </UiCard>
    </div>
</template>
