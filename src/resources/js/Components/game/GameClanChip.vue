<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import { computed } from 'vue';

// ClanChip (specs/18 §4): the clan's own badge from the API, unmodified, or our initials tile when
// it has none; then name, the account's role and the clan level.
const props = defineProps<{ clan: App.Domain.PlayerAccounts.Data.AccountClanData }>();

const detail = computed(() => [props.clan.roleLabel, props.clan.level === null ? null : `Level ${props.clan.level}`].filter(Boolean).join(' · '));
</script>

<template>
    <span class="inline-flex min-w-0 items-center gap-2 rounded-md border border-line bg-surface-raised py-1 pr-3 pl-1">
        <GameAsset :asset="clan.badge" :size="32" />
        <span class="flex min-w-0 flex-col">
            <span class="truncate text-sm font-semibold text-fg">{{ clan.name }}</span>
            <span v-if="detail" class="truncate text-xs text-fg-secondary">{{ detail }}</span>
        </span>
    </span>
</template>
