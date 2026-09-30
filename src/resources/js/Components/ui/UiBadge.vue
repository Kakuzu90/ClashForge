<script setup lang="ts">
import { computed } from 'vue';

export type BadgeKind = 'verified' | 'featured' | 'role' | 'rarity';

const props = defineProps<{
    kind: BadgeKind;
    /** Visible text; defaults per kind. Colour never carries the meaning alone. */
    label?: string;
    role?: 'mod' | 'admin';
    rarity?: 'common' | 'rare' | 'epic' | 'legendary';
}>();

const text = computed(() => {
    if (props.label) {
        return props.label;
    }

    switch (props.kind) {
        case 'verified':
            return 'Verified';
        case 'featured':
            return 'Featured';
        case 'role':
            return props.role === 'admin' ? 'Admin' : 'Moderator';
        case 'rarity':
            return (props.rarity ?? 'common').replace(/^\w/, (c) => c.toUpperCase());
    }

    return '';
});

const rarityTone: Record<string, string> = {
    common: 'border-line-strong text-fg-secondary',
    rare: 'border-info text-fg',
    epic: 'border-accent text-fg',
    legendary: 'border-brand text-fg',
};

const classes = computed(() => {
    switch (props.kind) {
        case 'verified':
            return 'border-verified text-fg';
        case 'featured':
            return 'border-featured text-fg';
        case 'role':
            return props.role === 'admin' ? 'border-danger text-fg' : 'border-info text-fg';
        default:
            return rarityTone[props.rarity ?? 'common'];
    }
});
</script>

<template>
    <span class="inline-flex h-6 items-center gap-1 rounded-sm border bg-surface-raised px-2 text-xs uppercase" :class="classes">
        <svg v-if="kind === 'verified'" class="text-verified" width="14" height="14" viewBox="0 0 16 16" aria-hidden="true">
            <path
                d="M8 1.5l1.9 1.4 2.3-.1.7 2.2 1.9 1.3-.8 2.2.8 2.2-1.9 1.3-.7 2.2-2.3-.1L8 15.5l-1.9-1.4-2.3.1-.7-2.2L1.2 10.7 2 8.5l-.8-2.2 1.9-1.3.7-2.2 2.3.1z"
                fill="currentColor"
            />
            <path
                d="M5.2 8.3l1.9 1.9 3.7-3.9"
                fill="none"
                stroke="var(--text-on-gold)"
                stroke-width="1.6"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
        <svg v-else-if="kind === 'featured'" class="text-featured" width="14" height="14" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 1.8l1.8 4 4.4.4-3.3 2.9 1 4.3L8 11.1l-3.9 2.3 1-4.3-3.3-2.9 4.4-.4z" fill="currentColor" />
        </svg>
        {{ text }}
    </span>
</template>
