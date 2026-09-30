<script setup lang="ts">
import PageTitle from '@/Components/shell/PageTitle.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import SkipLink from '@/Components/shell/SkipLink.vue';
import Wordmark from '@/Components/shell/Wordmark.vue';
import UiToaster from '@/Components/ui/UiToaster.vue';
import { ref, useId } from 'vue';

// Intentionally plain (specs/18 §4 admin): body font, dense, no lift or glow. Footer disclaimer still applies.
// Below 768px the admin nav folds behind a Menu toggle so content keeps the full width.
const menuOpen = ref(false);
const navId = useId();
</script>

<template>
    <PageTitle />
    <SkipLink />
    <div class="flex min-h-dvh flex-col font-body">
        <header class="border-b border-line bg-surface">
            <div class="gutter-x flex h-12 items-center gap-3">
                <Wordmark compact />
                <span class="text-sm font-semibold text-fg-secondary">Admin</span>
                <button
                    v-if="$slots.nav"
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
        <div class="flex flex-1 flex-col md:flex-row">
            <nav
                v-if="$slots.nav"
                :id="navId"
                aria-label="Admin"
                class="border-b border-line-subtle p-2 md:block md:w-52 md:shrink-0 md:border-r md:border-b-0"
                :class="menuOpen ? 'block' : 'hidden'"
            >
                <slot name="nav" />
            </nav>
            <main id="main" tabindex="-1" class="gutter-x min-w-0 flex-1 py-4">
                <slot />
            </main>
        </div>
        <SiteFooter />
    </div>
    <UiToaster />
</template>
