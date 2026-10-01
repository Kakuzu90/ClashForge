<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import { useVisitError } from '@/Composables/useVisitError';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { edit } from '@/routes/settings/notifications';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Notifications.UnsubscribePageData;
const props = defineProps<{
    outcome: Props['outcome'];
    message: Props['message'];
    confirmUrl: Props['confirmUrl'];
}>();
const confirming = ref(false);
const error = ref<string | null>(null);
const visitError = useVisitError();

function unsubscribe() {
    if (props.confirmUrl && !confirming.value) {
        error.value = null;
        router.post(
            props.confirmUrl,
            {},
            {
                onStart: () => (confirming.value = true),
                onFinish: () => (confirming.value = false),
                onError: () => (error.value = 'We could not save that change. Try again.'),
            },
        );
    }
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <h1 class="font-display text-h1">
            {{ outcome === 'unsubscribed' ? 'Emails turned off' : outcome === 'invalid' ? 'Link not working' : 'Unsubscribe from emails' }}
        </h1>
        <UiCard v-if="outcome === 'pending' && confirmUrl" variant="flat" class="flex flex-col gap-4 p-5 sm:p-6">
            <p class="text-body">{{ message }}</p>
            <UiAlert v-if="error" kind="danger">{{ error }}</UiAlert>
            <UiAlert v-if="visitError" kind="danger">We could not save that change. Try again.</UiAlert>
            <UiButton block :loading="confirming" @click="unsubscribe">Turn off non-security emails</UiButton>
        </UiCard>
        <UiAlert v-else :kind="outcome === 'unsubscribed' ? 'success' : 'danger'">{{ message }}</UiAlert>
        <UiButton variant="ghost" :href="edit().url">Email preferences</UiButton>
    </div>
</template>
