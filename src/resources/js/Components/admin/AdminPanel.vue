<script setup lang="ts">
import { useId } from 'vue';

// One dashboard panel (specs/18 §4 admin register): a titled, plain box. The heading names the
// region for screen readers.
defineProps<{ title: string; description?: string }>();

const id = useId();
</script>

<template>
    <section class="flex flex-col gap-3 rounded-sm border border-line bg-surface p-4" :aria-labelledby="id">
        <div class="flex flex-col gap-1">
            <h2 :id="id" class="text-body font-semibold text-fg">{{ title }}</h2>
            <p v-if="description" class="text-sm text-fg-secondary">{{ description }}</p>
        </div>
        <slot />
    </section>
</template>
