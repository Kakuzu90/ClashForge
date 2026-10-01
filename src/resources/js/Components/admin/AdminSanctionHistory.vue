<script setup lang="ts">
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import { formatDateTime } from '@/Composables/useDateTime';

type Sanction = App.Domain.Moderation.Data.SanctionData;

// specs/12 §4 "prior sanctions": every suspension and ban on the account, newest first, with what
// the account holder was told and what staff noted.
defineProps<{ sanctions: Sanction[] }>();

const tone: Record<string, PillTone> = { active: 'danger', lifted: 'neutral', ended: 'neutral' };
</script>

<template>
    <p v-if="sanctions.length === 0" class="text-sm text-fg-secondary">No sanctions on this account.</p>
    <ol v-else class="flex flex-col divide-y divide-line-subtle rounded-sm border border-line">
        <li v-for="(sanction, index) in sanctions" :key="index" class="flex flex-col gap-2 p-3 text-sm">
            <p class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-fg">{{ sanction.typeLabel }}</span>
                <UiPill :label="sanction.stateLabel" :tone="tone[sanction.state] ?? 'neutral'" />
                <span class="text-fg-secondary">{{ sanction.reasonLabel }}</span>
            </p>
            <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-[max-content_1fr]">
                <dt class="font-medium text-fg-secondary">Told them</dt>
                <dd class="break-words text-fg">{{ sanction.publicReason }}</dd>
                <dt class="font-medium text-fg-secondary">Internal note</dt>
                <dd class="break-words whitespace-pre-line text-fg">{{ sanction.internalNote }}</dd>
                <dt class="font-medium text-fg-secondary">Issued</dt>
                <dd class="text-fg">
                    <time :datetime="sanction.startsAt">{{ formatDateTime(sanction.startsAt) }}</time
                    >{{ ' ' }}by
                    {{ sanction.issuedBy ?? 'a deleted account' }}
                </dd>
                <dt class="font-medium text-fg-secondary">Ends</dt>
                <dd class="text-fg">
                    <time v-if="sanction.endsAt" :datetime="sanction.endsAt">{{ formatDateTime(sanction.endsAt) }}</time>
                    <template v-else>No end date</template>
                </dd>
                <template v-if="sanction.liftedAt">
                    <dt class="font-medium text-fg-secondary">Lifted</dt>
                    <dd class="text-fg">
                        <time :datetime="sanction.liftedAt">{{ formatDateTime(sanction.liftedAt) }}</time
                        >{{ ' ' }}by {{ sanction.liftedBy ?? 'a deleted account'
                        }}<template v-if="sanction.liftNote">: {{ sanction.liftNote }}</template>
                    </dd>
                </template>
            </dl>
        </li>
    </ol>
</template>
