<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import { usePageProps } from '@/Composables/usePageProps';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { register } from '@/routes';
import { computed } from 'vue';

defineOptions({ layout: PublicLayout });

// specs/18 §6 Home hero: the value proposition, and for guests the way in.
const { auth } = usePageProps();
const guest = computed(() => !auth.value?.user);
</script>

<template>
    <section class="py-10 md:py-16">
        <h1 class="font-display text-display">Clash Commons</h1>
        <p class="mt-3 max-w-xl text-body text-fg-secondary">
            Base layouts, verified player cards and clan recruitment for Clash of Clans players. The first features are on the way.
        </p>
        <UiButton v-if="guest" class="mt-6" :href="register().url">Create your account</UiButton>
    </section>
</template>
