<script setup lang="ts">
import { computed } from 'vue';

// StatBlock (specs/18 §4): a big tabular number with its label below. Use inside a <dl>; the label
// comes first in reading order. A fixed locale keeps the server-rendered and hydrated text equal.
// `delta` adds the change chip (+142 green, -30 red) with `deltaLabel` saying what it compares
// with; a null value reads "Not available" (specs/23 §5). The count-up arrives with P3-04.
const props = defineProps<{
    value: number | null;
    label: string;
    delta?: number | null;
    deltaLabel?: string;
}>();

const format = new Intl.NumberFormat('en');
const formatted = computed(() => (props.value === null ? null : format.format(props.value)));
const deltaText = computed(() => {
    if (props.delta === undefined || props.delta === null || props.delta === 0) {
        return null;
    }

    return `${props.delta > 0 ? '+' : '-'}${format.format(Math.abs(props.delta))}`;
});
</script>

<template>
    <div class="flex flex-col gap-1">
        <dt class="order-last text-xs text-fg-secondary uppercase">{{ label }}</dt>
        <dd class="flex flex-wrap items-baseline gap-2">
            <span v-if="formatted !== null" class="font-display text-stat text-fg tabular-nums">{{ formatted }}</span>
            <span v-else class="text-sm text-fg-muted">Not available</span>
            <span
                v-if="deltaText"
                class="rounded-sm border px-1.5 text-xs tabular-nums"
                :class="(delta ?? 0) > 0 ? 'border-success bg-success/15 text-success-fg' : 'border-danger bg-danger/15 text-danger-fg'"
            >
                <span v-if="deltaLabel" class="sr-only">{{ deltaLabel }}: </span>{{ deltaText }}
            </span>
        </dd>
    </div>
</template>
