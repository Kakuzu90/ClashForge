<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { useToast } from '@/Composables/useToast';

// Dev-only filler that makes a layout's stickiness, gutters and footer visible. Not product copy.
defineProps<{ name: string; notes: string[] }>();
const { push } = useToast();
</script>

<template>
    <p class="text-sm font-semibold text-fg-muted uppercase">Dev only</p>
    <h1 class="font-display text-h1">{{ name }} layout</h1>
    <ul class="mt-3 list-disc pl-5 text-sm text-fg-secondary">
        <li v-for="note in notes" :key="note">{{ note }}</li>
    </ul>
    <div class="mt-4"><UiButton size="sm" variant="secondary" @click="push('Toast from the layout toaster')">Push a toast</UiButton></div>
    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <UiCard v-for="n in 9" :key="n" variant="raised" class="p-4">
            <UiSkeleton variant="text" :lines="3" />
        </UiCard>
    </div>
</template>
