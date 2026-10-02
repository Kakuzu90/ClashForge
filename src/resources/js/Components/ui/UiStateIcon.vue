<script setup lang="ts">
export type StateKind = 'info' | 'success' | 'warning' | 'danger' | 'reward' | 'maintenance';

defineProps<{ kind: StateKind }>();

const colour: Record<StateKind, string> = {
    info: 'text-info',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    reward: 'text-brand',
    maintenance: 'text-warning',
};
</script>

<template>
    <!-- The shape carries the kind as well as the colour (colour never carries meaning alone). Shared by toasts and alerts. -->
    <svg :class="colour[kind]" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
        <template v-if="kind === 'info'">
            <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2" />
            <path d="M10 9v5M10 6.2v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </template>
        <template v-else-if="kind === 'success'">
            <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2" />
            <path d="M6.5 10.2l2.4 2.4 4.6-4.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </template>
        <template v-else-if="kind === 'warning'">
            <path d="M10 1.8l8.2 8.2-8.2 8.2-8.2-8.2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
            <path d="M10 6.5v4.5M10 13.6v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </template>
        <template v-else-if="kind === 'danger'">
            <path d="M10 2.5l8 14.5H2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
            <path d="M10 8v4M10 14.4v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </template>
        <template v-else-if="kind === 'maintenance'">
            <path
                d="M12.6 2.4a4 4 0 0 0-4.9 5.3l-5.4 5.4a1.6 1.6 0 0 0 2.3 2.3l5.4-5.4a4 4 0 0 0 5.3-4.9l-2.4 2.4-2.1-.6-.6-2.1z"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linejoin="round"
            />
        </template>
        <path v-else d="M10 2l2.4 5.1 5.6.6-4.2 3.8 1.2 5.5L10 14.2 5 17l1.2-5.5L2 7.7l5.6-.6z" fill="currentColor" />
    </svg>
</template>
