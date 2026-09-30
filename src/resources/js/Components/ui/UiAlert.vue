<script setup lang="ts">
import UiStateIcon from './UiStateIcon.vue';

export type AlertKind = 'info' | 'success' | 'warning' | 'danger';

// Inline, in-flow message (specs/18 §4 Alert / Banner). Danger interrupts (`alert`); the rest wait (`status`).
withDefaults(defineProps<{ kind?: AlertKind; title?: string }>(), { kind: 'info' });

const accent: Record<AlertKind, string> = {
    info: 'border-l-info',
    success: 'border-l-success',
    warning: 'border-l-warning',
    danger: 'border-l-danger',
};

const prefix: Record<AlertKind, string> = { info: 'Info', success: 'Success', warning: 'Warning', danger: 'Error' };
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
    </div>
</template>
