<script setup lang="ts">
import CocApiBanner from '@/Components/shell/CocApiBanner.vue';
import AdminNav from '@/Components/admin/AdminNav.vue';
import PageTitle from '@/Components/shell/PageTitle.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import SkipLink from '@/Components/shell/SkipLink.vue';
import Wordmark from '@/Components/shell/Wordmark.vue';
import UiToaster from '@/Components/ui/UiToaster.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { adminNav, visibleAdminNav } from '@/navigation';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { computed, ref, useId, watch } from 'vue';

// Intentionally plain (specs/18 §4 admin): body font, dense, no lift or glow. Footer disclaimer still applies.
// Below 768px the admin nav folds behind a Menu toggle so content keeps the full width. From 768px
// the top bar and sidebar stay put and only the content column scrolls (owner decision, 2026-10-02).
const menuOpen = ref(false);
const navId = useId();

const { can, url } = usePageProps();
const navItems = computed(() => visibleAdminNav(adminNav, can.value));
// A persistent layout outlives the visit, so close the folded menu once a section is picked.
watch(url, () => (menuOpen.value = false));
</script>

<template>
    <PageTitle />
    <SkipLink />
    <div class="flex min-h-dvh flex-col font-body md:h-dvh md:overflow-hidden">
        <header class="sticky top-0 z-200 shrink-0 border-b border-line bg-surface">
            <div class="gutter-x flex h-12 items-center gap-3">
                <Wordmark compact />
                <span class="text-sm font-semibold text-fg-secondary">Admin</span>
                <button
                    v-if="navItems.length > 0"
                    type="button"
                    class="ml-2 inline-flex min-h-11 items-center rounded-sm px-3 text-sm text-fg-secondary hover:bg-surface-raised hover:text-fg md:hidden"
                    :aria-expanded="menuOpen ? 'true' : 'false'"
                    :aria-controls="navId"
                    @click="menuOpen = !menuOpen"
                >
                    Menu
                </button>
                <div class="ml-auto flex items-center gap-2"><slot name="header-actions" /></div>
            </div>
        </header>
        <div class="flex flex-1 flex-col md:min-h-0 md:flex-row">
            <nav
                v-if="navItems.length > 0"
                :id="navId"
                aria-label="Admin"
                class="flex-col gap-2 border-b border-line-subtle p-2 md:flex md:w-52 md:shrink-0 md:overflow-y-auto md:border-r md:border-b-0"
                :class="menuOpen ? 'flex' : 'hidden'"
            >
                <AdminNav :items="navItems" :current-url="url" />
                <Link
                    :href="home().url"
                    class="flex min-h-11 items-center gap-2 rounded-sm border-t border-line-subtle px-3 text-sm text-fg-secondary hover:bg-surface-raised hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus md:mt-auto"
                >
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M10 3.5L5.5 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to site
                </Link>
            </nav>
            <div class="flex min-w-0 flex-1 flex-col md:overflow-y-auto">
                <main id="main" tabindex="-1" class="gutter-x min-w-0 flex-1 py-4">
                    <CocApiBanner class="mb-4" />
                    <slot />
                </main>
                <SiteFooter />
            </div>
        </div>
    </div>
    <UiToaster />
</template>
