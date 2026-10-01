<script setup lang="ts">
import BottomNav from '@/Components/shell/BottomNav.vue';
import SideNav from '@/Components/shell/SideNav.vue';
import PageTitle from '@/Components/shell/PageTitle.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import SiteHeader from '@/Components/shell/SiteHeader.vue';
import SkipLink from '@/Components/shell/SkipLink.vue';
import TopNav from '@/Components/shell/TopNav.vue';
import VerifyEmailBanner from '@/Components/shell/VerifyEmailBanner.vue';
import UiToaster from '@/Components/ui/UiToaster.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { primaryNav, visibleNavItems } from '@/navigation';
import { computed } from 'vue';

// specs/18 §5: bottom tabs < 768px, top nav 768–1023px, left sidebar ≥ 1024px.
const { can, url } = usePageProps();
const items = computed(() => visibleNavItems(primaryNav, can.value));
</script>

<template>
    <PageTitle />
    <SkipLink />
    <div class="min-h-dvh lg:flex">
        <SideNav class="hidden lg:flex" :items="items" :current-url="url" />
        <div class="flex min-w-0 flex-1 flex-col">
            <SiteHeader hide-wordmark-on-desktop>
                <template #actions><slot name="header-actions" /></template>
                <template #below><TopNav class="hidden md:block lg:hidden" :items="items" :current-url="url" /></template>
            </SiteHeader>
            <main
                id="main"
                tabindex="-1"
                class="gutter-x mx-auto w-full max-w-[1200px] flex-1 scroll-mt-16 pt-6 md:scroll-mt-28 md:pt-8 lg:scroll-mt-16"
            >
                <VerifyEmailBanner class="mb-6" />
                <slot />
            </main>
            <!-- Bottom padding keeps the footer clear of the fixed tab bar on mobile. -->
            <SiteFooter class="pb-[calc(56px+env(safe-area-inset-bottom))] md:pb-0" />
        </div>
    </div>
    <BottomNav class="md:hidden" :items="items" :current-url="url" />
    <UiToaster above-tab-bar />
</template>
