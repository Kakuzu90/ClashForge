<script setup lang="ts">
import { computed } from 'vue';

// StatBlock (specs/18 §4): a big tabular number with its label below. Use inside a <dl>; the label
// comes first in reading order. A fixed locale keeps the server-rendered and hydrated text equal.
const props = defineProps<{
    value: number;
    label: string;
}>();

const formatted = computed(() => new Intl.NumberFormat('en').format(props.value));
</script>

<template>
    <div class="flex flex-col gap-1">
        <dt class="order-last text-xs text-fg-secondary uppercase">{{ label }}</dt>
        <dd class="font-display text-stat text-fg tabular-nums">{{ formatted }}</dd>
    </div>
</template>
