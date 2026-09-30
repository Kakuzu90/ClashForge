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
            '768 to 1023px: nav row under the top bar',
            '1024px and up: left sidebar, collapsible to 64px (remembered per browser)',
            'Nav shows only items whose page exists (Phase 0: Home)',
        ]"
    />
</template>
