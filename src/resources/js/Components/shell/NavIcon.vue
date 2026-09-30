<script setup lang="ts">
import type { NavIconName } from '@/navigation';

// Original 24px glyphs for platform navigation only (specs/18 §9: never game assets). Heavy 2.5px stroke
// to match the chunky depth-bordered UI; the active item gets a tinted fill so state is a shape change too.
defineProps<{ name: NavIconName; active?: boolean }>();
</script>

<template>
    <svg
        width="24"
        height="24"
        viewBox="0 0 24 24"
        :fill="active ? 'currentColor' : 'none'"
        :fill-opacity="active ? 0.3 : undefined"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <template v-if="name === 'home'">
            <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1z" />
        </template>
        <template v-else-if="name === 'bases'">
            <path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z" />
            <path d="M12 12l8-4.5M12 12v9M12 12L4 7.5" />
        </template>
        <template v-else-if="name === 'recruit'">
            <path d="M6 21V4" />
            <path d="M6 4h11l-2.5 4L17 12H6" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="6.5" />
            <path d="M16 16l4.5 4.5" />
        </template>
        <template v-else-if="name === 'market'">
            <path d="M4 9l1.5-5h13L20 9" />
            <path d="M4 9h16v11H4z" />
            <path d="M9 13h6" />
        </template>
        <template v-else>
            <circle cx="12" cy="8.5" r="4" />
            <path d="M4.5 20.5c1.2-3.6 4-5.5 7.5-5.5s6.3 1.9 7.5 5.5" />
        </template>
    </svg>
</template>
