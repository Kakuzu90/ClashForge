<script setup lang="ts">
import { isActive, type ResolvedNavItem } from '@/navigation';
import { Link } from '@inertiajs/vue3';

// From 768px the primary nav sits inside the top bar, after the wordmark, as plain text links; the current one
// is gold with a bar along the bottom edge of the header (owner decision, 2026-10-02).
defineProps<{ items: ResolvedNavItem[]; currentUrl: string }>();
</script>

<template>
    <nav aria-label="Primary" class="self-stretch">
        <ul class="flex h-full items-stretch gap-2">
            <li v-for="item in items" :key="item.key" class="flex">
                <Link
                    :href="item.url"
                    :aria-current="isActive(currentUrl, item.url) ? 'page' : undefined"
                    class="relative flex items-center px-4 text-body font-semibold focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus"
                    :class="isActive(currentUrl, item.url) ? 'text-brand' : 'text-fg-secondary hover:text-fg'"
                >
                    {{ item.label }}
                    <span v-if="isActive(currentUrl, item.url)" class="absolute inset-x-2 -bottom-px h-1 rounded-t-sm bg-brand" aria-hidden="true" />
                </Link>
            </li>
        </ul>
    </nav>
</template>
