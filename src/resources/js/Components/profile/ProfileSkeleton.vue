<script setup lang="ts">
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';

// specs/18 §6 Player profile, loading: cover and avatar first, then the tab content.
</script>

<template>
    <div class="flex flex-col gap-6 py-6 md:py-8">
        <span role="status" class="sr-only">Loading profile</span>
        <div class="flex items-center gap-4 rounded-xl border border-line bg-surface p-4 sm:p-6" aria-hidden="true">
            <UiAvatar name="" :size="96" loading />
            <div class="flex-1"><UiSkeleton :lines="2" /></div>
        </div>
        <div class="grid grid-cols-3 gap-4" aria-hidden="true">
            <UiSkeleton v-for="n in 3" :key="n" variant="stat" />
        </div>
        <UiSkeleton variant="card" />
    </div>
</template>
