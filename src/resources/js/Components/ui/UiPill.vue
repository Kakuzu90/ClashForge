<script setup lang="ts">
import { computed } from 'vue';

export type PillTone =
    'neutral' | 'brand' | 'accent' | 'success' | 'danger' | 'warning' | 'info' | 'th-1' | 'th-2' | 'th-3' | 'th-4' | 'th-5' | 'th-6' | 'th-7';

const props = withDefaults(
    defineProps<{
        label: string;
        tone?: PillTone;
        /** Renders a toggle button with aria-pressed. */
        selectable?: boolean;
        selected?: boolean;
        removable?: boolean;
    }>(),
    { tone: 'neutral' },
);

const emit = defineEmits<{ toggle: []; remove: [] }>();

// Text stays in the primary colour; the tone only tints border and background, so contrast never
// depends on the hue (colour never carries meaning alone, specs/18 §3).
const tones: Record<PillTone, string> = {
    neutral: 'border-line-strong bg-surface-raised',
    brand: 'border-brand bg-brand/15',
    accent: 'border-accent bg-accent/15',
    success: 'border-success bg-success/15',
    danger: 'border-danger bg-danger/15',
    warning: 'border-warning bg-warning/15',
    info: 'border-info bg-info/15',
    'th-1': 'border-th-1 bg-th-1/15',
    'th-2': 'border-th-2 bg-th-2/15',
    'th-3': 'border-th-3 bg-th-3/15',
    'th-4': 'border-th-4 bg-th-4/15',
    'th-5': 'border-th-5 bg-th-5/15',
    'th-6': 'border-th-6 bg-th-6/15',
    'th-7': 'border-th-7 bg-th-7/15',
};

const classes = computed(() => [
    'inline-flex h-7 items-center gap-1 whitespace-nowrap rounded-sm border px-2 text-xs uppercase text-fg',
    tones[props.tone],
    props.selectable ? 'hit-target cursor-pointer hover:bg-surface-hover' : '',
    props.selected ? 'ring-2 ring-brand' : '',
]);
</script>

<template>
    <button v-if="selectable" type="button" :class="classes" :aria-pressed="selected ? 'true' : 'false'" @click="emit('toggle')">
        {{ label }}
    </button>
    <span v-else :class="classes">
        {{ label }}
        <button
            v-if="removable"
            type="button"
            class="hit-target -mr-1 inline-flex size-5 items-center justify-center rounded-sm hover:bg-surface-hover"
            :aria-label="`Remove ${label}`"
            @click="emit('remove')"
        >
            <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
                <path d="M3 3l6 6M9 3l-6 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
            </svg>
        </button>
    </span>
</template>
