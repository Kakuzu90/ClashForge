<script setup lang="ts">
import GamePlayerCard from '@/Components/game/GamePlayerCard.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';

// specs/18 §6 Player profile, loading: cover and avatar first, then the featured card, the stats
// and the account cards.
</script>

<template>
    <div class="flex flex-col gap-6 py-6 md:py-8">
        <span role="status" class="sr-only">Loading profile</span>
        <div class="flex items-center gap-4 rounded-xl border border-line bg-surface p-4 sm:p-6" aria-hidden="true">
            <UiAvatar name="" :size="96" loading />
            <div class="flex-1"><UiSkeleton :lines="2" /></div>
        </div>
        <div aria-hidden="true"><UiSkeleton variant="card" /></div>
        <div class="grid grid-cols-3 gap-4" aria-hidden="true">
            <UiSkeleton v-for="n in 3" :key="n" variant="stat" />
        </div>
        <div class="grid gap-4 md:grid-cols-2" aria-hidden="true">
            <GamePlayerCard v-for="n in 2" :key="n" :card="null" />
        </div>
    </div>
</template>
