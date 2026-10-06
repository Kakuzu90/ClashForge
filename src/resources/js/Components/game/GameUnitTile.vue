<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import GameFireRing from '@/Components/game/GameFireRing.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import { computed, ref } from 'vue';

// One unit in a progression grid (specs/18 §6): the art in a framed square with the level chip on
// its bottom-left corner, as the game's profile shows it. A maxed chip turns gold and burns
// (GameFireRing). A locked unit (not unlocked yet) shows grayed out with no chip, as in the game
// (owner exception to specs/18 §2.1 condition 2, recorded there). A hero with equipment is a button that
// opens its equipment, like tapping it in the game; the modal stays mounted, so the equipment art
// loads once.
const props = defineProps<{ unit: App.Domain.PlayerAccounts.Data.ProgressionUnitData }>();

const open = ref(false);
const opens = computed(() => props.unit.equipment.length > 0);
const description = computed(() => {
    const { name, level, maxLevel, maxed, locked } = props.unit;
    if (locked) return `${name}, not unlocked`;
    const state = maxed ? `level ${level}, maxed` : maxLevel === null ? `level ${level}` : `level ${level} of ${maxLevel}`;

    return `${name}, ${state}${opens.value ? ', show equipment' : ''}`;
});
</script>

<template>
    <component
        :is="opens ? 'button' : 'div'"
        :type="opens ? 'button' : undefined"
        :aria-haspopup="opens ? 'dialog' : undefined"
        class="group flex w-full justify-center"
        :class="opens ? 'cursor-pointer rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-focus' : ''"
        :title="unit.name"
        @click="opens && (open = true)"
    >
        <span class="sr-only">{{ description }}</span>
        <span
            aria-hidden="true"
            class="relative grid size-16 place-items-center rounded-md border-2 border-b-4 bg-surface-raised transition-transform duration-200 ease-out"
            :class="[
                unit.locked ? 'border-line bg-page' : unit.maxed ? 'border-brand-shadow' : 'border-line-strong',
                opens ? 'group-hover:-translate-y-0.5' : '',
            ]"
        >
            <span class="grid" :class="unit.locked ? 'opacity-50 grayscale' : ''">
                <GameAsset :asset="unit.asset" :size="48" />
            </span>
            <span v-if="!unit.locked" class="absolute -bottom-2 -left-2">
                <span v-if="unit.maxed" class="relative block">
                    <GameFireRing />
                    <span
                        class="relative grid h-5 w-7 place-items-center rounded-sm border border-brand-shadow bg-surface font-display text-sm text-brand tabular-nums"
                    >
                        {{ unit.level }}
                    </span>
                </span>
                <span
                    v-else
                    class="grid h-5 min-w-6 place-items-center rounded-sm border border-line-strong bg-surface px-1 text-xs font-bold text-fg tabular-nums"
                >
                    {{ unit.level }}
                </span>
            </span>
        </span>
    </component>

    <UiModal v-if="opens" v-model:open="open" :title="`${unit.name} equipment`" keep-mounted>
        <ul class="grid grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-x-3 gap-y-4 pb-2 pl-2">
            <li v-for="item in unit.equipment" :key="item.name">
                <GameUnitTile :unit="item" />
            </li>
        </ul>
    </UiModal>
</template>
