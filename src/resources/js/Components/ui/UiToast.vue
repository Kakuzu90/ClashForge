<script setup lang="ts">
import type { ToastKind } from '@/Composables/useToast';
import { computed } from 'vue';
import UiStateIcon from './UiStateIcon.vue';

const props = defineProps<{ kind: ToastKind; title: string; body?: string }>();
const emit = defineEmits<{ dismiss: [] }>();

const accents: Record<ToastKind, string> = {
    info: 'border-l-info',
    success: 'border-l-success',
    danger: 'border-l-danger',
    reward: 'border-l-brand border-brand',
};

const prefix: Record<ToastKind, string> = { info: 'Info', success: 'Success', danger: 'Error', reward: 'Reward' };

const classes = computed(() => [
    'pointer-events-auto flex w-full items-start gap-3 rounded-lg border border-line border-l-4 bg-surface-raised p-4 shadow-modal',
    accents[props.kind],
    props.kind === 'reward' ? 'animate-reward-in' : 'animate-toast-in',
]);
</script>

<template>
    <div :class="classes" :role="kind === 'danger' ? 'alert' : 'status'">
        <UiStateIcon class="mt-0.5 shrink-0" :kind="kind" />
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
