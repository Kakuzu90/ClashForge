<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { home } from '@/routes';

defineOptions({ layout: AppLayout });

// One page for an unknown, hidden, banned or pending-deletion profile, so the copy never hints
// which it is (specs/11 "Account enumeration").
</script>

<template>
    <div class="py-6 md:py-8">
        <h1 class="sr-only">Profile not found</h1>
        <UiEmptyState title="We couldn't find that profile" body="Check the username in the link, or head back to the home page.">
            <template #action>
                <UiButton :href="home().url">Go to the home page</UiButton>
            </template>
        </UiEmptyState>
    </div>
</template>
