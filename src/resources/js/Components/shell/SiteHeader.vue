<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { headerLinks, visibleHeaderLinks } from '@/navigation';
import { computed } from 'vue';
import AccountControls from './AccountControls.vue';
import NotificationBell from './NotificationBell.vue';
import Wordmark from './Wordmark.vue';

// Sticky top bar (specs/18 §5). Search slots into `actions` once it exists; signed in, the order is
// staff link (Admin or Reports) → bell → account controls, which always come last.

// Signed in, the account controls need the room below 640px, so the wordmark shortens to CC there.
const { auth, can } = usePageProps();
const signedIn = computed(() => !!auth.value?.user);
const staffLinks = computed(() => (signedIn.value ? visibleHeaderLinks(headerLinks, can.value) : []));
</script>

<template>
    <header class="sticky top-0 z-200 border-b border-line-subtle bg-page/95 backdrop-blur-sm">
        <div class="gutter-x mx-auto flex h-14 w-full max-w-[1200px] items-center gap-4 md:gap-6">
            <div>
                <template v-if="signedIn">
                    <span class="sm:hidden"><Wordmark compact /></span>
                    <span class="hidden sm:inline"><Wordmark /></span>
                </template>
                <Wordmark v-else />
            </div>
            <div class="hidden self-stretch md:flex"><slot name="nav" /></div>
            <div class="ml-auto flex items-center gap-2">
                <slot name="actions" />
                <UiButton v-for="link in staffLinks" :key="link.key" variant="ghost" size="sm" :href="link.url">{{ link.label }}</UiButton>
                <NotificationBell v-if="signedIn" />
                <AccountControls />
            </div>
        </div>
    </header>
</template>
