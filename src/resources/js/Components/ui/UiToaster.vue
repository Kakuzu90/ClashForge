<script setup lang="ts">
import { useToast } from '@/Composables/useToast';
import UiToast from './UiToast.vue';

const { toasts, dismiss, pause, resume } = useToast();
</script>

<template>
    <!-- One region for all toasts; each toast carries its own status/alert role. Mounted once in the layout. -->
    <div
        class="pointer-events-none fixed inset-x-0 bottom-0 z-400 flex flex-col items-center gap-2 p-4 sm:items-end"
        role="region"
        aria-label="Notifications"
        @mouseenter="pause"
        @mouseleave="resume"
        @focusin="pause"
        @focusout="resume"
    >
        <UiToast
            v-for="toast in toasts"
            :key="toast.id"
            :kind="toast.kind"
            :title="toast.title"
            :body="toast.body"
            class="sm:max-w-sm"
            @dismiss="dismiss(toast.id)"
        />
    </div>
</template>
