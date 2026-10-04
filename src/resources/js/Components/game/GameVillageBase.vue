<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import GameProgressionGrid from '@/Components/game/GameProgressionGrid.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { computed } from 'vue';

// One village's base panel on the account page (specs/18 §6), as the game's profile lays it out:
// the hall and the `side` groups (heroes, pets) on the left, the rest (troops, spells) on the
// right; stacked on a phone. `groups: null` is the loading state while the deferred grids arrive.
const props = defineProps<{
    hall: App.Domain.GameAssets.Data.GameAssetData | null;
    groups: App.Domain.PlayerAccounts.Data.ProgressionGroupData[] | null;
    side: string[];
}>();

const left = computed(() => (props.groups ?? []).filter((g) => props.side.includes(g.key)));
const right = computed(() => (props.groups ?? []).filter((g) => !props.side.includes(g.key)));
</script>

<template>
    <div class="grid gap-6 rounded-lg border-2 border-line-strong bg-surface p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <div class="flex flex-col gap-6">
            <figure v-if="hall" class="flex flex-col items-center gap-2 rounded-md bg-surface-raised p-4">
                <GameAsset :asset="hall" :size="128" :lazy="false" />
                <figcaption class="font-display text-h3 text-fg">{{ hall.alt }}</figcaption>
            </figure>
            <UiSkeleton v-if="groups === null" variant="media" :lines="1" label="Loading heroes" />
            <GameProgressionGrid v-for="group in left" :key="group.key" :group="group" />
        </div>
        <div class="flex flex-col gap-6">
            <UiSkeleton v-if="groups === null" variant="media" :lines="2" label="Loading troops and spells" />
            <UiEmptyState v-else-if="groups.length === 0" title="No unit data yet" body="Heroes, troops and spells show here after the next sync." />
            <GameProgressionGrid v-for="group in right" :key="group.key" :group="group" />
        </div>
    </div>
</template>
