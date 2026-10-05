<script setup lang="ts">
import UiBadge from '@/Components/ui/UiBadge.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiSteps from '@/Components/ui/UiSteps.vue';
import { useToast } from '@/Composables/useToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import { attach, featured } from '@/routes/accounts';
import { show } from '@/routes/profile';
import { useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Accounts.VerifiedPageData;
const props = defineProps<{ account: Props['account']; firstAccount: Props['firstAccount']; profileUsername: Props['profileUsername'] }>();

const { push } = useToast();

// The "set as featured" prompt (specs/18 §6), when the user already had a featured account.
const featureForm = useForm({});
function makeFeatured() {
    featureForm.put(featured(props.account.ulid).url, { preserveScroll: true });
}

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
            <div v-else-if="account.canFeature" class="flex flex-col gap-3 border-t border-line pt-3">
                <p class="text-body text-fg-secondary">Your featured account is listed first on your profile.</p>
                <div>
                    <UiButton size="sm" variant="secondary" :loading="featureForm.processing" @click="makeFeatured">Make this your featured account</UiButton>
                </div>
            </div>
        </UiCard>

        <div class="flex flex-col gap-3 sm:flex-row">
            <UiButton :href="show(profileUsername).url">Go to your profile</UiButton>
            <UiButton :href="attach().url" variant="secondary">Attach another account</UiButton>
        </div>
    </div>
</template>
