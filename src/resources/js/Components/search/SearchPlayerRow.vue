<script setup lang="ts">
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import { show as profile } from '@/routes/profile';
import { Link } from '@inertiajs/vue3';

// A player in search results: avatar, name, @username and the start of the bio. The whole row links
// to the profile.
defineProps<{ player: App.Domain.Users.Data.PlayerHitData }>();
</script>

<template>
    <Link
        :href="profile(player.username).url"
        class="flex items-start gap-3 rounded-lg border border-line-subtle bg-surface p-3 hover:border-line-strong focus-visible:outline-2 focus-visible:outline-focus"
    >
        <UiAvatar :name="player.displayName ?? player.username" :src="player.avatarUrl" :size="48" />
        <div class="min-w-0">
            <p class="truncate font-semibold text-fg">{{ player.displayName ?? `@${player.username}` }}</p>
            <p v-if="player.displayName" class="truncate text-sm text-fg-secondary">@{{ player.username }}</p>
            <p v-if="player.bio" class="mt-1 line-clamp-2 text-sm text-fg-secondary">{{ player.bio }}</p>
        </div>
    </Link>
</template>
