<script setup lang="ts">
import { isActive, type ResolvedNavItem } from '@/navigation';
import { Link } from '@inertiajs/vue3';
import NavIcon from './NavIcon.vue';

// Tablet (768–1023px): the bottom tab bar becomes a row under the top bar (specs/18 §5).
defineProps<{ items: ResolvedNavItem[]; currentUrl: string }>();
</script>

<template>
    <nav aria-label="Primary" class="border-t border-line-subtle">
        <ul class="gutter-x mx-auto flex w-full max-w-[1200px] gap-1">
            <li v-for="item in items" :key="item.key">
                <Link
                    :href="item.url"
                    :aria-current="isActive(currentUrl, item.url) ? 'page' : undefined"
                    class="flex min-h-11 items-center gap-2 border-b-2 px-3 text-sm font-semibold"
                    :class="isActive(currentUrl, item.url) ? 'border-brand text-fg' : 'border-transparent text-fg-secondary hover:text-fg'"
                >
                    <NavIcon :name="item.icon" :active="isActive(currentUrl, item.url)" />
                    {{ item.label }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
