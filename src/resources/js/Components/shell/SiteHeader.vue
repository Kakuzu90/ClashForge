<script setup lang="ts">
import Wordmark from './Wordmark.vue';

// Sticky top bar (specs/18 §5). Search, bell and user menu slot into `actions` once they exist.
defineProps<{ hideWordmarkOnDesktop?: boolean }>();
</script>

<template>
    <header class="sticky top-0 z-200 border-b border-line-subtle bg-page/95 backdrop-blur-sm">
        <div class="gutter-x mx-auto flex h-14 w-full max-w-[1200px] items-center justify-between gap-4">
            <div :class="hideWordmarkOnDesktop ? 'lg:invisible' : ''">
                <Wordmark />
            </div>
            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <slot name="below" />
    </header>
</template>
