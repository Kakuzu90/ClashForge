<script setup lang="ts">
import UiBadge from '@/Components/ui/UiBadge.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiSteps from '@/Components/ui/UiSteps.vue';
import { useToast } from '@/Composables/useToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import { attach } from '@/routes/accounts';
import { show } from '@/routes/profile';
import { onMounted } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Accounts.VerifiedPageData;
const props = defineProps<{ account: Props['account']; firstAccount: Props['firstAccount']; profileUsername: Props['profileUsername'] }>();

const { push } = useToast();

// The reward toast is for the first verified account only (specs/18 §4).
onMounted(() => {
    if (props.firstAccount) {
        push('First account verified', { kind: 'reward', body: `${props.account.name} now carries the verified badge on your profile.` });
    }
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-4">
            <h1 class="font-display text-h1">Account verified</h1>
            <UiSteps :steps="['Find your account', 'Prove it is yours', 'Done']" :current="3" label="Attach steps" />
        </header>

        <UiCard class="flex flex-col gap-3 p-4 sm:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-display text-h2 break-all text-fg">{{ account.name }}</p>
                <UiBadge kind="verified" />
                <UiBadge v-if="account.featured" kind="featured" />
            </div>
            <p class="font-mono text-sm text-fg-secondary">{{ account.tag }}</p>
            <p v-if="account.featured" class="text-body text-fg-secondary">This is now your featured account.</p>
        </UiCard>

        <div class="flex flex-col gap-3 sm:flex-row">
            <UiButton :href="show(profileUsername).url">Go to your profile</UiButton>
            <UiButton :href="attach().url" variant="secondary">Attach another account</UiButton>
        </div>
    </div>
</template>
