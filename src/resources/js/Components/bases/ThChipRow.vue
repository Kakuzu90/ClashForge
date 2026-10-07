<script setup lang="ts">
import UiPill from '@/Components/ui/UiPill.vue';
import { thLevels, thParam } from '@/Components/bases/feedQuery';
import { computed } from 'vue';

// The Town Hall chip row (specs/18 §6 Home feed): All, then each level, highest first. The signed-in
// default (the featured account's level ±1) shows as its own removable chip.
const props = defineProps<{
    thMin: number;
    thMax: number;
    selectedMin: number | null;
    selectedMax: number | null;
    fromAccount: boolean;
}>();

const emit = defineEmits<{ select: [range: [number, number] | null] }>();

const levels = computed(() => thLevels(props.thMin, props.thMax));
const range = computed(() => props.selectedMin !== null && props.selectedMax !== null && props.selectedMin !== props.selectedMax);
</script>

<template>
    <!-- Phones: one row that swipes sideways (no vertical scroll; the padding holds the 44px hit
         areas). From md the chips wrap, so nothing scrolls. -->
    <div class="-mx-4 overflow-x-auto overflow-y-hidden px-4 py-2 [scrollbar-width:none] md:mx-0 md:overflow-visible md:px-0" role="group" aria-label="Town Hall">
        <div class="flex w-max items-center gap-2 md:w-auto md:flex-wrap">
            <UiPill
                v-if="range && selectedMin !== null && selectedMax !== null"
                :label="fromAccount ? `TH ${thParam(selectedMin, selectedMax)}, your Town Hall` : `TH ${thParam(selectedMin, selectedMax)}`"
                tone="brand"
                removable
                @remove="emit('select', null)"
            />
            <UiPill label="All" selectable :selected="selectedMin === null" @toggle="emit('select', null)" />
            <UiPill
                v-for="level in levels"
                :key="level"
                :label="`TH ${level}`"
                selectable
                :selected="!range && selectedMin === level"
                @toggle="emit('select', [level, level])"
            />
        </div>
    </div>
</template>
