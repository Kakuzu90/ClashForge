<script setup lang="ts">
import LayoutPreviewContent from '@/Components/dev/LayoutPreviewContent.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });
</script>

<template>
    <LayoutPreviewContent
        name="Public"
        :notes="[
            'Sticky top bar with the wordmark',
            'Content max-width 1200px, 16px gutters on mobile, 24px from 768px',
            'Footer disclaimer on every page',
        ]"
    />
</template>
