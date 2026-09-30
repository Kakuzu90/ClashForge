<script setup lang="ts">
import { computed } from 'vue';

export type CardVariant = 'flat' | 'raised' | 'interactive' | 'feature';

const props = withDefaults(defineProps<{ variant?: CardVariant; selected?: boolean; as?: string }>(), { variant: 'raised', as: 'div' });

const variants: Record<CardVariant, string> = {
    flat: 'rounded-lg bg-surface border border-line-subtle',
    raised: 'rounded-lg bg-surface border border-line border-b-[3px] border-b-line-strong',
    // Hover lift + depth 3→5px (specs/18 §7).
    interactive:
        'rounded-lg bg-surface border border-line border-b-[3px] border-b-line-strong transition-[transform,border-color,border-bottom-width] duration-200 ease-out hover:-translate-y-0.5 hover:border-line-strong hover:border-b-[5px]',
    feature: 'rounded-xl bg-surface-raised border border-line border-b-[3px] border-b-line-strong',
};

const classes = computed(() => [
    variants[props.variant],
    'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus',
    props.selected ? 'ring-2 ring-brand' : '',
]);
</script>

<template>
    <component :is="as" :class="classes">
        <!-- Announce selection in reading order; interactive children carry aria-pressed/aria-selected themselves. -->
        <span v-if="selected" class="sr-only">Selected.</span>
        <slot />
    </component>
</template>
