<script setup lang="ts">
import { computed, ref, watch } from 'vue';

export type AvatarSize = 24 | 32 | 48 | 64 | 96 | 128;

const props = withDefaults(
    defineProps<{
        name: string;
        src?: string | null;
        size?: AvatarSize;
        verified?: boolean;
        loading?: boolean;
        /** Screen-reader text while loading, e.g. "Loading profile". */
        loadingLabel?: string;
    }>(),
    { size: 48, src: null },
);

const failed = ref(false);
watch(
    () => props.src,
    () => (failed.value = false),
);

const initials = computed(
    () =>
        props.name
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() ?? '')
            .join('') || '?',
);

const showImage = computed(() => !!props.src && !failed.value);

// The display font is never used below 16px (specs/18 §3): small avatars use Inter semibold, 12px floor.
const initialsFont = computed(() => (props.size >= 48 ? 'font-display' : 'font-body font-semibold'));
const box = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    fontSize: `${Math.max(12, Math.round(props.size * 0.4))}px`,
}));
</script>

<template>
    <span v-if="loading" class="inline-block">
        <span v-if="loadingLabel" role="status" class="sr-only">{{ loadingLabel }}</span>
        <span
            class="block animate-shimmer rounded-full bg-linear-90 from-surface-raised via-surface-hover to-surface-raised bg-[length:200%_100%]"
            :style="box"
            aria-hidden="true"
        />
    </span>
    <span
        v-else
        class="relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-raised text-fg"
        :class="[initialsFont, verified ? 'ring-2 ring-verified ring-offset-2 ring-offset-page' : '']"
        :style="box"
    >
        <img
            v-if="showImage"
            :src="src!"
            :alt="name"
            :width="size"
            :height="size"
            loading="lazy"
            class="size-full object-cover"
            @error="failed = true"
        />
        <span v-else role="img" :aria-label="name">{{ initials }}</span>
    </span>
</template>
