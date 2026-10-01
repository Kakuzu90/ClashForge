<script setup lang="ts">
import { isActive } from '@/navigation';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// The admin left nav (specs/18 §6): plain links. Every section sits under /admin, so the longest
// matching link is the current one (Dashboard is not current on /admin/audit).
const props = defineProps<{ items: { key: string; label: string; url: string }[]; currentUrl: string }>();

const currentKey = computed(
    () => props.items.filter((item) => isActive(props.currentUrl, item.url)).sort((a, b) => b.url.length - a.url.length)[0]?.key ?? null,
);
</script>

<template>
    <ul class="flex flex-col gap-1">
        <li v-for="item in items" :key="item.key">
            <Link
                :href="item.url"
                :aria-current="item.key === currentKey ? 'page' : undefined"
                class="flex min-h-11 items-center rounded-sm px-3 text-sm"
                :class="
                    item.key === currentKey ? 'bg-surface-raised font-semibold text-fg' : 'text-fg-secondary hover:bg-surface-raised hover:text-fg'
                "
            >
                {{ item.label }}
            </Link>
        </li>
    </ul>
</template>
