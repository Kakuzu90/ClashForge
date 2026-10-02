<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import { thTier, type ThTier } from '@/Composables/useThTier';
import { computed } from 'vue';

export type ThBadgeSize = 'sm' | 'md' | 'lg';

// ThBadge (specs/18 §4): our own shape in the tier colour, the numeral always visible. The optional
// Town Hall image beside it is the game asset, unmodified; without it the badge reads the same.
const props = withDefaults(
    defineProps<{
        level: number;
        size?: ThBadgeSize;
        /** The resolver's Town Hall asset; shown at `lg` only. */
        asset?: App.Domain.GameAssets.Data.GameAssetData | null;
        builder?: boolean;
    }>(),
    { size: 'md', asset: null, builder: false },
);

const tier = computed(() => thTier(props.level));
const box = { sm: 'size-7 text-sm', md: 'size-10 text-body', lg: 'size-14 text-h3' } as const;

// Static class strings, so Tailwind sees every tier colour.
const fill: Record<ThTier, string> = {
    1: 'fill-th-1/20 stroke-th-1',
    2: 'fill-th-2/20 stroke-th-2',
    3: 'fill-th-3/20 stroke-th-3',
    4: 'fill-th-4/20 stroke-th-4',
    5: 'fill-th-5/20 stroke-th-5',
    6: 'fill-th-6/20 stroke-th-6',
    7: 'fill-th-7/20 stroke-th-7',
};
const label = computed(() => `${props.builder ? 'Builder Hall' : 'Town Hall'} ${props.level}`);
const showAsset = computed(() => props.size === 'lg' && props.asset !== null && props.asset.url !== null);
</script>

<template>
    <span role="img" :aria-label="label" class="inline-flex shrink-0 items-center gap-2">
        <GameAsset v-if="showAsset && asset" aria-hidden="true" :asset="asset" :size="64" :lazy="false" />
        <span class="relative inline-flex items-center justify-center" :class="box[size]" aria-hidden="true">
            <svg class="absolute inset-0 size-full overflow-visible" viewBox="0 0 32 32">
                <!-- The top tier's gold ring (DESIGN.md owner decision: status highlight). -->
                <path v-if="tier === 7" d="M16 0.5l14 8v15l-14 8-14-8v-15z" class="fill-none stroke-brand" stroke-width="1.5" />
                <path d="M16 2.5l12 7v13l-12 7-12-7v-13z" :class="fill[tier]" stroke-width="2.5" stroke-linejoin="round" />
            </svg>
            <span class="relative font-display leading-none text-fg tabular-nums">{{ level }}</span>
        </span>
    </span>
</template>
