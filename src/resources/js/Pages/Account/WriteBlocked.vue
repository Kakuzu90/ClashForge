<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import { home } from '@/routes';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Account.AccountStatusPageData;

const props = defineProps<{ status: Props['status']; reason: Props['reason']; endsAt: Props['endsAt'] }>();

// Shown when account status blocks a change (specs/04 §1). The server decides; this only explains.
const copy: Partial<Record<Props['status'], { title: string; body: string }>> = {
    restricted: {
        title: 'Your account is restricted',
        body: 'You can still read and edit your profile. Uploading, publishing, commenting, applying and messaging are off until the restriction ends.',
    },
    suspended: {
        title: 'Your account is suspended',
        body: 'Changes are off until the suspension ends.',
    },
    pending_deletion: {
        title: 'Your account is scheduled for deletion',
        body: 'Changes are off while the deletion is pending.',
    },
};

const text = computed(() => copy[props.status] ?? { title: "You can't do this right now", body: 'Your account status does not allow this change.' });
const endsAt = computed(() => (props.endsAt ? formatDateTime(props.endsAt) : null));
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-12">
        <h1 class="font-display text-h1">{{ text.title }}</h1>
        <p class="text-body text-fg-secondary">{{ text.body }}</p>

        <UiCard v-if="reason || endsAt" class="p-5 sm:p-6">
            <dl class="flex flex-col gap-4 text-body">
                <div v-if="reason">
                    <dt class="text-sm font-semibold text-fg-secondary">Reason</dt>
                    <dd class="mt-1 text-fg">{{ reason }}</dd>
                </div>
                <div v-if="endsAt">
                    <dt class="text-sm font-semibold text-fg-secondary">Ends</dt>
                    <dd class="mt-1 text-fg">{{ endsAt }}</dd>
                </div>
            </dl>
        </UiCard>

        <div>
            <UiButton variant="secondary" :href="home().url">Back to home</UiButton>
        </div>
    </div>
</template>
