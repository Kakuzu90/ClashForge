<script setup lang="ts">
import UiCard from '@/Components/ui/UiCard.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Account.AccountStatusPageData;

const props = defineProps<{ status: Props['status']; reason: Props['reason']; endsAt: Props['endsAt'] }>();

const endsAt = computed(() => (props.endsAt ? formatDateTime(props.endsAt) : null));
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-12">
        <h1 class="font-display text-h1">Your account is suspended</h1>
        <p class="text-body text-fg-secondary">
            While it is suspended, you can't post or browse other players' content. You can still sign out, and your settings and notifications stay
            open.
        </p>

        <UiCard class="p-5 sm:p-6">
            <dl class="flex flex-col gap-4 text-body">
                <div>
                    <dt class="text-sm font-semibold text-fg-secondary">Reason</dt>
                    <dd class="mt-1 text-fg">{{ reason ?? 'No reason was given.' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-semibold text-fg-secondary">Ends</dt>
                    <dd class="mt-1 text-fg">{{ endsAt ?? 'No end date is set.' }}</dd>
                </div>
            </dl>
        </UiCard>
    </div>
</template>
