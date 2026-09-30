<script setup lang="ts">
import type { ToastKind } from '@/Composables/useToast';
import { computed } from 'vue';

const props = defineProps<{ kind: ToastKind; title: string; body?: string }>();
const emit = defineEmits<{ dismiss: [] }>();

const accents: Record<ToastKind, string> = {
    info: 'border-l-info',
    success: 'border-l-success',
    danger: 'border-l-danger',
    reward: 'border-l-brand border-brand',
};

const iconColour: Record<ToastKind, string> = { info: 'text-info', success: 'text-success', danger: 'text-danger', reward: 'text-brand' };

const prefix: Record<ToastKind, string> = { info: 'Info', success: 'Success', danger: 'Error', reward: 'Reward' };

const classes = computed(() => [
    'pointer-events-auto flex w-full items-start gap-3 rounded-lg border border-line border-l-4 bg-surface-raised p-4 shadow-modal',
    accents[props.kind],
    props.kind === 'reward' ? 'animate-reward-in' : 'animate-toast-in',
]);
</script>

<template>
    <div :class="classes" :role="kind === 'danger' ? 'alert' : 'status'">
        <!-- The shape carries the kind as well as the colour (colour never carries meaning alone). -->
        <svg class="mt-0.5 shrink-0" :class="iconColour[kind]" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
            <template v-if="kind === 'info'">
                <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2" />
                <path d="M10 9v5M10 6.2v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </template>
            <template v-else-if="kind === 'success'">
                <circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2" />
                <path
                    d="M6.5 10.2l2.4 2.4 4.6-4.9"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </template>
            <template v-else-if="kind === 'danger'">
                <path d="M10 2.5l8 14.5H2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                <path d="M10 8v4M10 14.4v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </template>
            <path v-else d="M10 2l2.4 5.1 5.6.6-4.2 3.8 1.2 5.5L10 14.2 5 17l1.2-5.5L2 7.7l5.6-.6z" fill="currentColor" />
        </svg>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-fg">
                <span class="sr-only">{{ prefix[kind] }}: </span>{{ title }}
            </p>
            <p v-if="body" class="mt-1 text-sm text-fg-secondary">{{ body }}</p>
        </div>
        <button
            type="button"
            class="hit-target -m-2 inline-flex size-10 shrink-0 items-center justify-center rounded-md text-fg-secondary hover:bg-surface-hover hover:text-fg"
            aria-label="Dismiss notification"
            @click="emit('dismiss')"
        >
            <svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>
    </div>
</template>
