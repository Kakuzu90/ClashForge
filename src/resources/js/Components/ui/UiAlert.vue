<script setup lang="ts">
import UiStateIcon from './UiStateIcon.vue';

export type AlertKind = 'info' | 'success' | 'warning' | 'danger' | 'maintenance';

// Inline, in-flow message (specs/18 §4 Alert / Banner). Danger interrupts (`alert`); the rest wait (`status`).
// `dismissible` adds a close button; the parent decides what dismissing means.
withDefaults(defineProps<{ kind?: AlertKind; title?: string; dismissible?: boolean }>(), { kind: 'info' });
defineEmits<{ dismiss: [] }>();

const accent: Record<AlertKind, string> = {
    info: 'border-l-info',
    success: 'border-l-success',
    warning: 'border-l-warning',
    danger: 'border-l-danger',
    maintenance: 'border-l-warning',
};

const prefix: Record<AlertKind, string> = { info: 'Info', success: 'Success', warning: 'Warning', danger: 'Error', maintenance: 'Maintenance' };
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-lg border border-l-4 border-line bg-surface-raised p-4"
        :class="accent[kind]"
        :role="kind === 'danger' ? 'alert' : 'status'"
    >
        <UiStateIcon class="mt-0.5 shrink-0" :kind="kind" />
        <div class="min-w-0 flex-1 text-body text-fg">
            <p v-if="title" class="font-semibold">
                <span class="sr-only">{{ prefix[kind] }}: </span>{{ title }}
            </p>
            <span v-else class="sr-only">{{ prefix[kind] }}: </span>
            <div :class="title ? 'mt-1 text-sm text-fg-secondary' : ''"><slot /></div>
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="hit-target -m-1 inline-flex size-8 shrink-0 items-center justify-center rounded-sm text-fg-muted hover:bg-surface hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            aria-label="Dismiss"
            @click="$emit('dismiss')"
        >
            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
        </button>
    </div>
</template>
