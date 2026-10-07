<script setup lang="ts">
import GameThBadge from '@/Components/game/GameThBadge.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import { show as account } from '@/routes/accounts';
import { show as profile } from '@/routes/profile';
import { Link } from '@inertiajs/vue3';

// PlayerCard `mini` (specs/18 §4): inline attribution on a base card. The author's avatar and name
// link to their profile; the credited account, when shown, links to its page. Kept to one line so
// every card in a grid has the same height.
defineProps<{
    author: App.Domain.Users.Data.AuthorData;
    credit: App.Domain.PlayerAccounts.Data.CreditedAccountData | null;
}>();
</script>

<template>
    <div class="relative z-10 flex min-w-0 items-center gap-2 text-sm">
        <UiAvatar :name="author.displayName ?? author.username" :src="author.avatarUrl" :size="24" />
        <Link :href="profile(author.username).url" class="min-w-0 truncate text-fg hover:underline">
            {{ author.displayName ?? `@${author.username}` }}
        </Link>
        <template v-if="credit">
            <span class="text-fg-muted" aria-hidden="true">·</span>
            <Link :href="account(credit.ulid).url" class="flex min-w-0 items-center gap-1 text-fg-secondary hover:underline">
                <GameThBadge v-if="credit.thLevel !== null" :level="credit.thLevel" size="sm" />
                <span class="truncate">{{ credit.name }}</span>
            </Link>
        </template>
    </div>
</template>
