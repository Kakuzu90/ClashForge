<script setup lang="ts">
export type SkeletonVariant = 'text' | 'card' | 'avatar' | 'stat' | 'media';

withDefaults(
    defineProps<{
        variant?: SkeletonVariant;
        lines?: number;
        /** Screen-reader text for the loading state, e.g. "Loading bases". Omit when a parent announces it. */
        label?: string;
    }>(),
    { variant: 'text', lines: 3 },
);

// Shimmer turns into a static tint under prefers-reduced-motion (base stylesheet).
const shimmer = 'animate-shimmer bg-[length:200%_100%] bg-linear-90 from-surface-raised via-surface-hover to-surface-raised';
</script>

<template>
    <div>
        <span v-if="label" role="status" class="sr-only">{{ label }}</span>
        <div aria-hidden="true">
            <div v-if="variant === 'text'" class="flex flex-col gap-2">
                <span v-for="n in lines" :key="n" class="block h-4 rounded-sm" :class="[shimmer, n === lines && lines > 1 ? 'w-3/5' : 'w-full']" />
            </div>
            <span v-else-if="variant === 'avatar'" class="block size-12 rounded-full" :class="shimmer" />
            <div v-else-if="variant === 'stat'" class="flex flex-col gap-2">
                <span class="block h-8 w-20 rounded-sm" :class="shimmer" />
                <span class="block h-3 w-16 rounded-sm" :class="shimmer" />
            </div>
            <span v-else-if="variant === 'media'" class="block aspect-video w-full rounded-lg" :class="shimmer" />
            <div v-else class="rounded-lg border border-line bg-surface p-4">
                <span class="block aspect-video w-full rounded-sm" :class="shimmer" />
                <span class="mt-4 block h-5 w-3/4 rounded-sm" :class="shimmer" />
                <span class="mt-2 block h-4 w-1/2 rounded-sm" :class="shimmer" />
            </div>
        </div>
    </div>
</template>
