<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Keeps the client-side <title> in sync with the server's PageMeta after hydration and on visits,
// and announces the new page to screen readers after each Inertia visit (the initial load is
// announced by the browser itself).
const page = usePage();
const title = computed(() => page.props.meta?.title ?? '');
const announcement = ref('');
let stop: (() => void) | null = null;

onMounted(() => {
    stop = router.on('navigate', () => {
        // Clear first so the same title twice in a row is still announced.
        announcement.value = '';
        requestAnimationFrame(() => {
            announcement.value = title.value || 'Clash Commons';
        });
    });
});

onBeforeUnmount(() => stop?.());
</script>

<template>
    <Head :title="title" />
    <div role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</div>
</template>
