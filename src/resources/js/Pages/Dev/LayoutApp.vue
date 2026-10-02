<script setup lang="ts">
import LayoutPreviewContent from '@/Components/dev/LayoutPreviewContent.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <LayoutPreviewContent
        name="App"
        :notes="[
            'Below 768px: bottom tab bar (56px + safe area)',
            '768px and up: text links inside the top bar after the wordmark, the current one underlined',
            'Nav shows only items whose page exists (Phase 0: Home)',
        ]"
    />
</template>
