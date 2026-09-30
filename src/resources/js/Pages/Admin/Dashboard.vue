<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { home } from '@/routes';

defineOptions({ layout: AdminLayout });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-h2 font-semibold">Dashboard</h1>
            <UiButton variant="ghost" size="sm" :href="home().url">Back to site</UiButton>
        </div>
        <UiEmptyState title="Nothing to review yet" body="Open reports, disputes and new sign-ups will show here once the admin tools arrive." />
    </div>
</template>
