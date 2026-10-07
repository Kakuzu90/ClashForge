<script setup lang="ts">
import { formatCount } from '@/Composables/useNumberFormat';
import { computed } from 'vue';

export type ResourceKind = 'likes' | 'copies' | 'views' | 'comments';

// ResourceCounter (specs/18 §4): our own icon and a count for a platform action. Never a game asset:
// these count what people do here, not anything in the game.
const props = defineProps<{ kind: ResourceKind; count: number }>();

const words: Record<ResourceKind, [string, string]> = {
    likes: ['like', 'likes'],
    copies: ['copy', 'copies'],
    views: ['view', 'views'],
    comments: ['comment', 'comments'],
};

const label = computed(() => `${formatCount(props.count)} ${words[props.kind][props.count === 1 ? 0 : 1]}`);
</script>

<template>
    <span class="inline-flex items-center gap-1 text-sm text-fg-secondary tabular-nums" :aria-label="label">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path v-if="kind === 'likes'" d="M8 13.5S2 10 2 6a3 3 0 0 1 6-1 3 3 0 0 1 6 1c0 4-6 7.5-6 7.5z" />
            <template v-else-if="kind === 'copies'">
                <rect x="5" y="5" width="8.5" height="8.5" rx="1.5" />
                <path d="M3 10.5V3.5A1.5 1.5 0 0 1 4.5 2h6" />
            </template>
            <template v-else-if="kind === 'views'">
                <path d="M1.5 8S4 3.5 8 3.5 14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8z" />
                <circle cx="8" cy="8" r="2" />
            </template>
            <path v-else d="M2.5 3.5h11v7h-6l-3 2.5v-2.5h-2z" />
        </svg>
        <span aria-hidden="true">{{ formatCount(count) }}</span>
    </span>
</template>
