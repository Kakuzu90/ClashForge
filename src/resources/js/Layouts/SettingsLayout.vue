<script setup lang="ts">
import { usePageProps } from '@/Composables/usePageProps';
import { isActive, settingsNav } from '@/navigation';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// Settings pages (specs/18 §6): a plain sub-nav beside dense forms. Nested inside AppLayout.
const { url } = usePageProps();
const links = computed(() => settingsNav.map((link) => ({ ...link, url: link.href() })));
</script>

<template>
    <div class="flex flex-col gap-6 py-6 md:py-8">
        <h1 class="font-display text-h1">Settings</h1>
        <div class="flex flex-col gap-6 md:flex-row md:gap-10">
            <nav aria-label="Settings" class="md:w-48 md:shrink-0">
                <ul class="flex gap-1 overflow-x-auto md:flex-col">
                    <li v-for="link in links" :key="link.key">
                        <Link
                            :href="link.url"
                            :aria-current="isActive(url, link.url) ? 'page' : undefined"
                            class="flex min-h-11 items-center rounded-md px-3 text-body font-semibold whitespace-nowrap"
                            :class="
                                isActive(url, link.url) ? 'bg-surface-raised text-brand' : 'text-fg-secondary hover:bg-surface-raised hover:text-fg'
                            "
                        >
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </nav>
            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </div>
</template>
