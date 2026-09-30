<script setup lang="ts">
defineProps<{ title: string; body?: string }>();
</script>

<template>
    <div class="flex flex-col items-center px-4 py-10 text-center">
        <div v-if="$slots.illustration" class="mb-4" aria-hidden="true">
            <slot name="illustration" />
        </div>
        <h2 class="font-display text-h2 text-fg">{{ title }}</h2>
        <p v-if="body" class="mt-2 max-w-sm text-body text-fg-secondary">{{ body }}</p>
        <div v-if="$slots.action" class="mt-6">
            <slot name="action" />
        </div>
    </div>
</template>
