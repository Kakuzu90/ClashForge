<script setup lang="ts">
import GameUnitTile from '@/Components/game/GameUnitTile.vue';
import { useId } from 'vue';

// One progression grid (specs/18 §6): a heading and its units as GameUnitTiles. Order and grouping
// come from the server (catalogue order, P2-04).
defineProps<{ group: App.Domain.PlayerAccounts.Data.ProgressionGroupData }>();

const headingId = useId();
</script>

<template>
    <section :aria-labelledby="headingId">
        <h3 :id="headingId" class="font-display text-h3 text-fg">{{ group.label }}</h3>
        <ul class="mt-3 grid grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-x-3 gap-y-4 pb-2 pl-2">
            <li v-for="unit in group.units" :key="unit.name">
                <GameUnitTile :unit="unit" />
            </li>
        </ul>
    </section>
</template>
