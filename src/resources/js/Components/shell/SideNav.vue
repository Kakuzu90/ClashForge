<script setup lang="ts">
import { isActive, type ResolvedNavItem } from '@/navigation';
import { Link } from '@inertiajs/vue3';
import { onMounted, ref, useId } from 'vue';
import NavIcon from './NavIcon.vue';
import Wordmark from './Wordmark.vue';

// Desktop (≥1024px) left sidebar: 240px, collapsible to 64px icons (specs/18 §5).
defineProps<{ items: ResolvedNavItem[]; currentUrl: string }>();

const STORAGE_KEY = 'shell.sidebar.collapsed';
const collapsed = ref(false);
// Width animates only on a user toggle, never when the remembered state is restored on load.
const animate = ref(false);
const listId = useId();

// Per-browser convenience; read after mount so SSR and first client render match.
onMounted(() => {
    try {
        collapsed.value = localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        // storage unavailable (private mode): stay expanded
    }
});

function toggle() {
    animate.value = true;
    collapsed.value = !collapsed.value;
    try {
        localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0');
    } catch {
        // storage unavailable: the choice applies to this page view only
    }
}
</script>

<template>
    <aside
        class="sticky top-0 flex h-dvh shrink-0 flex-col border-r border-line-subtle bg-surface"
        :class="[collapsed ? 'w-16' : 'w-60', animate ? 'transition-[width] duration-200 ease-out' : '']"
    >
        <div class="flex h-14 items-center px-4">
            <Wordmark :compact="collapsed" />
        </div>
        <nav aria-label="Primary" class="flex-1 px-2 py-4">
            <ul :id="listId" class="flex flex-col gap-1">
                <li v-for="item in items" :key="item.key">
                    <Link
                        :href="item.url"
                        :aria-current="isActive(currentUrl, item.url) ? 'page' : undefined"
                        :title="collapsed ? item.label : undefined"
                        class="flex min-h-11 items-center gap-3 rounded-md px-3 text-body font-semibold"
                        :class="
                            isActive(currentUrl, item.url)
                                ? 'bg-surface-raised text-brand'
                                : 'text-fg-secondary hover:bg-surface-raised hover:text-fg'
                        "
                    >
                        <NavIcon :name="item.icon" :active="isActive(currentUrl, item.url)" />
                        <span :class="collapsed ? 'sr-only' : ''">{{ item.label }}</span>
                    </Link>
                </li>
            </ul>
        </nav>
        <div class="border-t border-line-subtle p-2">
            <button
                type="button"
                class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-sm text-fg-secondary hover:bg-surface-raised hover:text-fg"
                :aria-expanded="collapsed ? 'false' : 'true'"
                :aria-controls="listId"
                @click="toggle"
            >
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 20 20"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    aria-hidden="true"
                    :class="collapsed ? 'rotate-180' : ''"
                >
                    <path d="M12.5 4.5L7 10l5.5 5.5" />
                </svg>
                <span :class="collapsed ? 'sr-only' : ''">Sidebar</span>
            </button>
        </div>
    </aside>
</template>
