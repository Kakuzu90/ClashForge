<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{
    /** Always required: names the task for screen readers, shown unless hideLabel. */
    label: string;
    /** 0 to 100. Omit (or null) for an indeterminate bar. */
    value?: number | null;
    hideLabel?: boolean;
}>();

const labelId = useId();
const percent = computed(() => (props.value === undefined || props.value === null ? null : Math.min(100, Math.max(0, Math.round(props.value)))));
</script>

<template>
    <div class="flex flex-col gap-1">
        <div class="flex items-baseline justify-between gap-2 text-sm text-fg-secondary" :class="hideLabel ? 'sr-only' : ''">
            <span :id="labelId" class="min-w-0 truncate">{{ label }}</span>
            <span v-if="percent !== null" class="tabular-nums" aria-hidden="true">{{ percent }}%</span>
        </div>
        <div
            role="progressbar"
            :aria-labelledby="labelId"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="percent ?? undefined"
            class="h-2 overflow-hidden rounded-full border border-line bg-surface-raised"
        >
            <div v-if="percent !== null" class="h-full bg-brand" :style="{ width: `${percent}%` }" />
            <!-- Indeterminate: the skeleton shimmer, static under prefers-reduced-motion. -->
            <div v-else class="h-full w-full animate-shimmer bg-linear-90 from-surface-raised via-brand to-surface-raised bg-[length:200%_100%]" />
        </div>
    </div>
</template>
