<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';

// Flames around a maxed unit's level chip (specs/18 §7). The canvas extends 12px past the chip on
// every side and sits behind it; the renderer loads only once the ring scrolls into view.
const canvas = useTemplateRef<HTMLCanvasElement>('canvas');

let disposed = false;
let cleanup: (() => void) | undefined;
let observer: IntersectionObserver | undefined;

onMounted(() => {
    const element = canvas.value;
    if (!element || typeof IntersectionObserver === 'undefined') return;

    observer = new IntersectionObserver(([entry]) => {
        if (!entry?.isIntersecting) return;
        observer?.disconnect();
        void import('./fireRing')
            .then(({ fireRing }) => {
                if (!disposed) cleanup = fireRing(element);
            })
            .catch(() => {});
    });
    observer.observe(element);
});

onBeforeUnmount(() => {
    disposed = true;
    observer?.disconnect();
    cleanup?.();
});
</script>

<template>
    <span aria-hidden="true" class="pointer-events-none absolute -inset-3 mix-blend-screen">
        <canvas ref="canvas" class="block size-full" width="60" height="44" />
    </span>
</template>
