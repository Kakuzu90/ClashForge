<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { store } from '@/routes/password/confirm';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.ConfirmPasswordPageData;

defineProps<{ minutes: Props['minutes'] }>();

const form = useForm({ password: '' });
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
        <div class="flex flex-col gap-2">
            <h1 class="font-display text-h1">Confirm your password</h1>
            <p class="text-body text-fg-secondary">This is a sensitive change, so enter your password first. You will not be asked again for {{ minutes }} minutes.</p>
        </div>

        <UiCard class="p-5 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <UiInput
                    v-model="form.password"
                    label="Password"
                    type="password"
                    autocomplete="current-password"
                    required
                    autofocus
                    :error="form.errors.password"
                />
                <UiButton type="submit" block :loading="form.processing">Confirm</UiButton>
            </form>
        </UiCard>
    </div>
</template>
