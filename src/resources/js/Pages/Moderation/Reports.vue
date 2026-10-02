<script setup lang="ts">
import UiCard from '@/Components/ui/UiCard.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// The moderators' report queue (specs/12). Reports start arriving with Moderation v1 (P3-06).
</script>

<template>
    <div class="flex flex-col gap-6 pb-10">
        <h1 class="font-display text-h1">Reports</h1>
        <UiCard variant="flat">
            <UiEmptyState title="No open reports" body="Reports from members land here for review. Nothing is waiting right now." />
        </UiCard>
    </div>
</template>
