<script setup lang="ts">
import { isActive, type ResolvedNavItem } from '@/navigation';
import { Link } from '@inertiajs/vue3';
import NavIcon from './NavIcon.vue';

// Mobile tab bar: 56px + safe-area inset (specs/18 §5).
defineProps<{ items: ResolvedNavItem[]; currentUrl: string }>();
</script>

<template>
    <nav
        aria-label="Primary"
        class="fixed inset-x-0 bottom-0 z-200 border-t border-line-subtle bg-surface pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)]"
    >
        <ul class="mx-auto flex h-14 max-w-md items-stretch justify-around">
            <li v-for="item in items" :key="item.key" class="flex flex-1">
                <Link
                    :href="item.url"
                    :aria-current="isActive(currentUrl, item.url) ? 'page' : undefined"
                    class="flex flex-1 flex-col items-center justify-center gap-0.5 text-xs font-semibold"
                    :class="isActive(currentUrl, item.url) ? 'text-brand' : 'text-fg-secondary hover:text-fg'"
                >
                    <!-- Active tab: pill + filled icon, not colour alone (specs/18 §3). -->
                    <span class="flex h-7 w-12 items-center justify-center rounded-full" :class="isActive(currentUrl, item.url) ? 'bg-brand/20' : ''">
                        <NavIcon :name="item.icon" :active="isActive(currentUrl, item.url)" />
                    </span>
                    <span class="normal-case">{{ item.label }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
