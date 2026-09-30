<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, useAttrs } from 'vue';
import UiSpinner from './UiSpinner.vue';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success';
export type ButtonSize = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant;
        size?: ButtonSize;
        block?: boolean;
        iconOnly?: boolean;
        loading?: boolean;
        disabled?: boolean;
        type?: 'button' | 'submit' | 'reset';
        /** Renders an Inertia link instead of a button. */
        href?: string;
    }>(),
    { variant: 'primary', size: 'md', type: 'button' },
);

const attrs = useAttrs();

onMounted(() => {
    if (props.iconOnly && !attrs['aria-label'] && !attrs['aria-labelledby']) {
        console.warn('[UiButton] icon-only buttons need an aria-label (specs/18 §4).');
    }
});

const inactive = computed(() => props.disabled || props.loading);

// Depth: solid bottom border that compresses on press (specs/18 §3, §7).
const variants: Record<ButtonVariant, string> = {
    primary: 'bg-brand text-fg-on-gold border-brand-shadow hover:bg-brand-hover active:bg-brand-press',
    secondary: 'bg-surface-raised text-fg border-line-strong hover:bg-surface-hover',
    ghost: 'bg-transparent text-fg border-transparent hover:bg-surface-raised',
    danger: 'bg-danger text-fg-on-gold border-danger-shadow hover:bg-danger-hover',
    success: 'bg-success text-fg-on-gold border-success-shadow hover:bg-success-hover',
};

const sizes: Record<ButtonSize, string> = {
    sm: 'h-8 text-sm',
    md: 'h-10 text-body',
    lg: 'h-12 text-body',
};

const padding: Record<ButtonSize, string> = { sm: 'px-3', md: 'px-4', lg: 'px-6' };
const square: Record<ButtonSize, string> = { sm: 'w-8', md: 'w-10', lg: 'w-12' };

const classes = computed(() => [
    // hit-target: 44px touch area around the spec 18 §4 visual sizes (DESIGN.md owner decision).
    'hit-target inline-flex select-none items-center justify-center gap-2 rounded-md font-semibold',
    'transition-[transform,border-width,background-color] duration-80',
    props.variant === 'ghost' ? 'border-b-0' : 'border-b-4',
    variants[props.variant],
    sizes[props.size],
    props.iconOnly ? square[props.size] : padding[props.size],
    props.block && !props.iconOnly ? 'w-full' : '',
    inactive.value
        ? 'cursor-not-allowed opacity-60'
        : props.variant === 'ghost'
          ? 'active:translate-y-px'
          : 'active:translate-y-[3px] active:border-b-[1px]',
]);
</script>

<template>
    <Link v-if="href && !inactive" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else :type="type" :class="classes" :disabled="inactive" :aria-busy="loading || undefined" :aria-disabled="inactive || undefined">
        <span :class="loading ? 'inline-flex items-center gap-2 opacity-0' : 'inline-flex items-center gap-2'">
            <slot />
        </span>
        <span v-if="loading" class="absolute inset-0 flex items-center justify-center">
            <UiSpinner :size="size === 'sm' ? 14 : 18" />
            <span class="sr-only">Loading</span>
        </span>
    </button>
</template>
