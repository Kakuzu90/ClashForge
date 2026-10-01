<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { home, login } from '@/routes';
import { notice } from '@/routes/verification';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.VerificationResultPageData;

// Where a verification link lands (FR-AUTH-3). Opening it names the account; the button confirms,
// so a mail scanner that opens the link confirms nothing.
const props = defineProps<{
    outcome: Props['outcome'];
    message: Props['message'];
    signedIn: Props['signedIn'];
    username: Props['username'];
    confirmUrl: Props['confirmUrl'];
}>();

const pending = computed(() => props.outcome === 'pending' && props.confirmUrl !== null);
const ok = computed(() => props.outcome === 'verified' || props.outcome === 'already_verified');
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
            <h1 class="font-display text-h1">Confirm your email</h1>
            <UiCard class="flex flex-col gap-4 p-5 sm:p-6">
                <p class="text-body">
                    This confirms the email for the account <span class="font-semibold break-all">{{ username }}</span>.
                </p>
                <p class="text-sm text-fg-secondary">Did not sign up? Close this page and nothing happens.</p>
                <UiButton block :loading="confirming" @click="confirm">Confirm my email</UiButton>
            </UiCard>
        </template>

        <template v-else>
            <h1 class="font-display text-h1">{{ ok ? 'Email confirmed' : 'Link not working' }}</h1>

            <UiAlert :kind="ok ? 'success' : 'danger'">{{ message }}</UiAlert>

            <div class="flex flex-wrap gap-3">
                <template v-if="ok">
                    <UiButton v-if="signedIn" :href="home().url">Continue</UiButton>
                    <UiButton v-else :href="login().url">Sign in</UiButton>
                </template>
                <template v-else>
                    <UiButton :href="notice().url">Get a new link</UiButton>
                    <UiButton variant="ghost" :href="home().url">Home</UiButton>
                </template>
            </div>
        </template>
    </div>
</template>
