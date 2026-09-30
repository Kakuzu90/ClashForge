<script setup lang="ts">
import PageTitle from '@/Components/shell/PageTitle.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import SiteHeader from '@/Components/shell/SiteHeader.vue';
import SkipLink from '@/Components/shell/SkipLink.vue';
import UiToaster from '@/Components/ui/UiToaster.vue';
</script>

<template>
    <PageTitle />
    <SkipLink />
    <div class="flex min-h-dvh flex-col">
        <SiteHeader>
            <template #actions><slot name="header-actions" /></template>
        </SiteHeader>
        <main id="main" tabindex="-1" class="gutter-x mx-auto w-full max-w-[1200px] flex-1 scroll-mt-16 pt-6 md:pt-8">
            <slot />
        </main>
        <SiteFooter />
    </div>
    <UiToaster />
</template>
