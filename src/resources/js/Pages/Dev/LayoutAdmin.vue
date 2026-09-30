<script setup lang="ts">
import LayoutPreviewContent from '@/Components/dev/LayoutPreviewContent.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
</script>

<template>
    <LayoutPreviewContent
        name="Admin"
        :notes="[
            'Intentionally plain: body font, dense, no lift or glow',
            'Optional admin nav column via the nav slot',
            'Footer disclaimer still present',
        ]"
    />
</template>
