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
            'Admin nav column from the ability flags; folds behind Menu below 768px',
            'Footer disclaimer still present',
        ]"
    />
</template>
