<script setup lang="ts">
import BottomNav from '@/Components/shell/BottomNav.vue';
import HeaderNav from '@/Components/shell/HeaderNav.vue';
import PageTitle from '@/Components/shell/PageTitle.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import CocApiBanner from '@/Components/shell/CocApiBanner.vue';
import SiteHeader from '@/Components/shell/SiteHeader.vue';
import SkipLink from '@/Components/shell/SkipLink.vue';
import VerifyEmailBanner from '@/Components/shell/VerifyEmailBanner.vue';
import UiToaster from '@/Components/ui/UiToaster.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { primaryNav, visibleNavItems } from '@/navigation';
import { computed } from 'vue';

// Horizontal at every width (owner decision, 2026-10-02): bottom tabs < 768px; from 768px, text
// links inside the top bar, right after the wordmark. No sidebar, no second nav row.
const { can, url } = usePageProps();
const items = computed(() => visibleNavItems(primaryNav, can.value));
</script>

<template>
    <PageTitle />
    <SkipLink />
    <div class="flex min-h-dvh flex-col">
        <SiteHeader>
            <template #actions><slot name="header-actions" /></template>
            <template #nav><HeaderNav :items="items" :current-url="url" /></template>
        </SiteHeader>
        <main id="main" tabindex="-1" class="gutter-x mx-auto w-full max-w-[1200px] flex-1 scroll-mt-16 pt-6 md:pt-8">
            <CocApiBanner class="mb-6" />
            <VerifyEmailBanner class="mb-6" />
            <slot />
        </main>
        <!-- Bottom padding keeps the footer clear of the fixed tab bar on mobile. -->
        <SiteFooter class="pb-[calc(56px+env(safe-area-inset-bottom))] md:pb-0" />
    </div>
    <BottomNav class="md:hidden" :items="items" :current-url="url" />
    <UiToaster above-tab-bar />
</template>
