<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import { useId } from 'vue';

// One progression grid (specs/18 §6): each unit's asset with a level chip; a maxed unit's chip is
// gold and says "Max". Order and grouping come from the server (catalogue order, P2-04).
defineProps<{ group: App.Domain.PlayerAccounts.Data.ProgressionGroupData }>();

const headingId = useId();

function description(unit: App.Domain.PlayerAccounts.Data.ProgressionUnitData): string {
    if (unit.maxed) return `${unit.name}, level ${unit.level}, maxed`;

    return unit.maxLevel === null ? `${unit.name}, level ${unit.level}` : `${unit.name}, level ${unit.level} of ${unit.maxLevel}`;
}
</script>

<template>
    <section :aria-labelledby="headingId">
        <h3 :id="headingId" class="font-display text-h3 text-fg">{{ group.label }}</h3>
        <ul class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-8">
            <li
                v-for="unit in group.units"
                :key="unit.name"
                class="flex flex-col items-center gap-1 rounded-md border bg-surface-raised p-2"
                :class="unit.maxed ? 'border-brand' : 'border-line'"
                :title="unit.name"
            >
                <span class="sr-only">{{ description(unit) }}</span>
                <GameAsset aria-hidden="true" :asset="unit.asset" :size="48" />
                <span
                    aria-hidden="true"
                    class="rounded-sm px-1.5 text-xs tabular-nums"
                    :class="unit.maxed ? 'bg-brand text-fg-on-gold' : 'bg-surface text-fg-secondary'"
                >
                    {{ unit.maxed ? 'Max' : `Lv ${unit.level}` }}
                </span>
            </li>
        </ul>
    </section>
</template>
