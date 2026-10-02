<script setup lang="ts">
import UiCard from '@/Components/ui/UiCard.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { formatCount } from '@/Composables/useNumberFormat';
import { thTone } from '@/Composables/useThTier';

// The "Is this you?" card (specs/09 §9 step 1): enough to catch a typo before the token step.
defineProps<{ player: App.Domain.PlayerAccounts.Data.CocPlayerPreviewData }>();
</script>

<template>
    <UiCard class="flex flex-col gap-3 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <p class="font-display text-h2 break-all text-fg">{{ player.name }}</p>
            <UiPill v-if="player.townHallLevel" :label="`TH ${player.townHallLevel}`" :tone="thTone(player.townHallLevel)" />
        </div>
        <p class="font-mono text-sm text-fg-secondary">{{ player.tag }}</p>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
            <div v-if="player.trophies !== null">
                <dt class="text-fg-muted">Trophies</dt>
                <dd class="font-semibold tabular-nums text-fg">{{ formatCount(player.trophies) }}</dd>
            </div>
            <div v-if="player.expLevel !== null">
                <dt class="text-fg-muted">XP level</dt>
                <dd class="font-semibold tabular-nums text-fg">{{ player.expLevel }}</dd>
            </div>
            <div v-if="player.leagueName">
                <dt class="text-fg-muted">League</dt>
                <dd class="font-semibold text-fg">{{ player.leagueName }}</dd>
            </div>
            <div v-if="player.clanName">
                <dt class="text-fg-muted">Clan</dt>
                <dd class="font-semibold break-words text-fg">{{ player.clanName }}</dd>
            </div>
        </dl>
        <p v-if="player.stale && player.fetchedAt" class="text-sm text-warning">
            The game is not answering, so this is saved data from {{ formatDateTime(player.fetchedAt) }}.
        </p>
    </UiCard>
</template>
