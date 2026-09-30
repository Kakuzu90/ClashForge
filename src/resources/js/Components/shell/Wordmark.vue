<script setup lang="ts">
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

// Text wordmark until a logo is designed (antislop R-23; never game assets in our identity, specs/18 §2.1).
defineProps<{ compact?: boolean }>();
</script>

<template>
    <Link :href="home().url" class="hit-target inline-flex items-center rounded-sm font-display leading-none text-fg" aria-label="Clash Commons home">
        <span v-if="compact" class="text-h3 text-brand" aria-hidden="true">CC</span>
        <span v-else class="text-h2" aria-hidden="true">Clash <span class="text-brand">Commons</span></span>
    </Link>
</template>
