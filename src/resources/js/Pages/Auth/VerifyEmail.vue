<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import { usePageProps } from '@/Composables/usePageProps';
import AppLayout from '@/Layouts/AppLayout.vue';
import { send } from '@/routes/verification';
import { useForm } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Auth.VerifyEmailPageData;

// FR-AUTH-3/4: what to do until the email is confirmed, and a fresh link on request.
defineProps<{ email: Props['email']; status: Props['status']; linkMinutes: Props['linkMinutes'] }>();

const { flash } = usePageProps();
const form = useForm({});

function resend() {
    form.post(send().url, { preserveScroll: true });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <h1 class="font-display text-h1">Confirm your email</h1>

        <UiAlert v-if="status" kind="success">{{ status }}</UiAlert>
        <UiAlert v-else-if="flash?.error" kind="warning">{{ flash.error }}</UiAlert>

        <UiCard class="flex flex-col gap-4 p-5 sm:p-6">
            <p class="text-body">
                We sent a link to <span class="font-semibold break-all">{{ email }}</span>. Open it to finish setting up your account.
            </p>
            <p class="text-sm text-fg-secondary">
                Until then you can browse and edit your settings, but not upload, post or link a Clash of Clans account. Links expire after
                {{ linkMinutes }} minutes.
            </p>
            <UiButton variant="secondary" block :loading="form.processing" @click="resend">Send a new link</UiButton>
        </UiCard>
    </div>
</template>
