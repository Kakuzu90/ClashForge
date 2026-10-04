<script setup lang="ts">
import { computed, ref, watch } from 'vue';

export type GameAssetSize = 24 | 32 | 48 | 64 | 128;

// Renders a Clash of Clans asset exactly as resolved by GameAssetResolver (specs/18 §2.3). The image
// is only ever scaled: no rounding, borders, filters or crops, which would modify it (18 §2.1 (2)).
const props = withDefaults(
    defineProps<{
        asset: App.Domain.GameAssets.Data.GameAssetData;
        size?: GameAssetSize;
        /** Set false above the fold. */
        lazy?: boolean;
    }>(),
    { size: 32, lazy: true },
);

const failed = ref(false);
watch(
    () => props.asset.url,
    () => (failed.value = false),
);

const showImage = computed(() => props.asset.url !== null && !failed.value);
const box = computed(() => ({ width: `${props.size}px`, height: `${props.size}px` }));
const shortStyle = computed(() => ({ fontSize: `${Math.max(9, Math.round(props.size * (props.asset.short.length > 2 ? 0.28 : 0.36)))}px` }));

// Our own placeholder shapes, one per kind, so a missing asset still reads as the right thing.
const shapes: Record<App.Domain.GameAssets.Enums.GameAssetKind, string> = {
    unit: 'M7 2.5h18a4.5 4.5 0 0 1 4.5 4.5v18a4.5 4.5 0 0 1-4.5 4.5H7A4.5 4.5 0 0 1 2.5 25V7A4.5 4.5 0 0 1 7 2.5z',
    town_hall: 'M16 2l12.5 7.2v13.6L16 30 3.5 22.8V9.2z',
    league: 'M16 2.5l12 4v9c0 7-5 11.5-12 14-7-2.5-12-7-12-14v-9z',
    clan_badge: 'M5 3.5h22v12.5c0 6.5-4.5 10.5-11 13.5C9.5 26.5 5 22.5 5 16z',
};
</script>

<template>
    <img
        v-if="showImage"
        :src="asset.url ?? undefined"
        :alt="asset.alt"
        :width="size"
        :height="size"
        :loading="lazy ? 'lazy' : undefined"
        decoding="async"
        class="shrink-0 object-contain"
        :style="box"
        @error="failed = true"
    />
    <span v-else role="img" :aria-label="asset.alt" class="relative inline-flex shrink-0 items-center justify-center" :style="box">
        <svg class="absolute inset-0 size-full" viewBox="0 0 32 32" aria-hidden="true">
            <path :d="shapes[asset.kind]" class="fill-surface-raised stroke-line-strong" stroke-width="2" stroke-linejoin="round" />
        </svg>
        <span aria-hidden="true" class="relative leading-none font-bold text-fg-secondary tabular-nums" :style="shortStyle">{{ asset.short }}</span>
    </span>
</template>
