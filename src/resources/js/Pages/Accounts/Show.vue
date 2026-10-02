<script setup lang="ts">
import GamePlayerCard from '@/Components/game/GamePlayerCard.vue';
import GameProgressionGrid from '@/Components/game/GameProgressionGrid.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import { formatDuration } from '@/Composables/useDateTime';
import { usePageProps } from '@/Composables/usePageProps';
import AppLayout from '@/Layouts/AppLayout.vue';
import { verify } from '@/routes/accounts';
import { Deferred } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

// The CoC account page (specs/18 §6). Everything renders from stored data, so it works while the
// game API is down (FR-COC-14); the grids load as a deferred prop behind their skeleton.
const props = defineProps<{
    account: App.Domain.PlayerAccounts.Data.AccountDetailData;
    progression?: App.Domain.PlayerAccounts.Data.ProgressionGroupData[];
}>();

const { cocApi } = usePageProps();
const card = computed(() => props.account.card);
const age = computed(() => (card.value.syncedAgeSeconds === null ? null : formatDuration(card.value.syncedAgeSeconds)));
const deltaLabel = computed(() => `Change over the last ${props.account.deltaDays} days`);
</script>

<template>
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-6 sm:py-8">
        <GamePlayerCard :card="card" variant="hero" />

        <div class="flex flex-col gap-3">
            <UiAlert v-if="cocApi" kind="warning" title="Game data is temporarily unavailable">
                {{ age === null ? 'Showing the last saved data.' : `Showing data from ${age} ago.` }}
            </UiAlert>
            <UiAlert v-if="account.notFound" kind="warning" title="Clash of Clans can't find this tag any more">
                It may have been renamed or deleted in game. The account stays verified.
            </UiAlert>
            <UiAlert v-else-if="card.stale && !cocApi" kind="warning" title="This data is out of date">
                {{ age === null ? 'This account has not been synced yet.' : `It was last updated ${age} ago.` }}
            </UiAlert>
            <UiAlert v-if="card.status === 'disputed'" kind="info" title="Ownership is under review">
                Someone has asked the admins to review who owns this account. It stays with its current owner until they decide.
                <template v-if="account.canVerify">
                    If it is yours, verifying it again with the in-game API token ends the review.
                    <div class="mt-3">
                        <UiButton :href="verify(card.ulid).url" size="sm">Verify this account</UiButton>
                    </div>
                </template>
            </UiAlert>
            <UiAlert v-if="card.status === 'suspended'" kind="danger" title="This account is suspended">
                The admins have suspended this account on Clash Commons. Only you can see it, and nobody can verify it until they release it.
            </UiAlert>
            <UiAlert v-if="card.status === 'unverified' && account.isOwn" kind="info" title="This account is not verified yet">
                Only you can see it until you verify it with the in-game API token.
                <div v-if="account.canVerify" class="mt-3">
                    <UiButton :href="verify(card.ulid).url" size="sm">Verify this account</UiButton>
                </div>
            </UiAlert>
        </div>

        <section aria-labelledby="account-stats">
            <h2 id="account-stats" class="sr-only">Stats</h2>
            <dl class="grid grid-cols-2 gap-4 rounded-lg border border-line bg-surface p-4 sm:grid-cols-4">
                <UiStatBlock
                    v-for="stat in account.stats"
                    :key="stat.key"
                    :label="stat.label"
                    :value="stat.value"
                    :delta="stat.delta"
                    :delta-label="deltaLabel"
                />
            </dl>
        </section>

        <section aria-labelledby="account-progression" class="flex flex-col gap-6">
            <h2 id="account-progression" class="font-display text-h2 text-fg">Progression</h2>
            <Deferred data="progression">
                <template #fallback>
                    <UiSkeleton variant="media" :lines="2" label="Loading progression" />
                </template>
                <template v-if="progression && progression.length > 0">
                    <GameProgressionGrid v-for="group in progression" :key="group.key" :group="group" />
                </template>
                <UiEmptyState v-else title="No unit data yet" body="Units, heroes and spells show here after the next sync." />
            </Deferred>
        </section>

        <footer class="border-t border-line pt-4 text-sm text-fg-muted">
            {{ age === null ? 'Not synced yet.' : `Game data updated ${age} ago.` }}
        </footer>
    </div>
</template>
