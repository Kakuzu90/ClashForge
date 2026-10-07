<script setup lang="ts">
import GameBaseCard from '@/Components/game/GameBaseCard.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import type { VisitError } from '@/Composables/useVisitError';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// The feed's card grid (specs/18 §5 grid, §6 Home feed states): 1 column on phones, 2 on tablets,
// 3 on desktop and 4 from 1440px. "Load more" asks for the next page only; Inertia appends it.
const props = defineProps<{
    cards: App.Domain.Bases.Data.BaseCardData[];
    nextCursor: string | null;
    /** The current filters as a query, sent with the cursor so the server reads the same feed. */
    query: Record<string, string>;
    categories: App.Domain.Bases.Data.FeedOptionData[];
    /** A filter visit is in flight: show skeletons instead of the stale grid. */
    loading: boolean;
    error: VisitError | null;
    filtered: boolean;
}>();

const emit = defineEmits<{ reset: []; retry: [] }>();

const loadingMore = ref(false);
const labels = computed(() => Object.fromEntries(props.categories.map((c) => [c.value, c.label])));

function loadMore() {
    if (!props.nextCursor) return;
    loadingMore.value = true;
    router.reload({
        only: ['cards', 'nextCursor'],
        data: { ...props.query, cursor: props.nextCursor },
        preserveUrl: true,
        onFinish: () => (loadingMore.value = false),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <UiAlert v-if="error && !loadingMore" kind="danger" title="Bases didn't load">
            <template v-if="error.throttled">Too many requests in a row. Wait a minute and try again.</template>
            <template v-else-if="error.requestId">
                If it keeps failing, quote request id <span class="font-semibold">{{ error.requestId }}</span>.
            </template>
            <template v-else>Check your connection and try again.</template>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="emit('retry')">Try again</UiButton>
        </UiAlert>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" aria-busy="true">
            <span class="sr-only" role="status">Loading bases</span>
            <GameBaseCard v-for="n in 6" :key="n" :card="null" />
        </div>

        <UiEmptyState
            v-else-if="cards.length === 0"
            title="No bases match these filters"
            :body="filtered ? 'Try another Town Hall or fewer filters.' : 'No bases have been published yet.'"
        >
            <template v-if="filtered" #action>
                <div class="flex flex-col items-center gap-3">
                    <UiButton variant="secondary" @click="emit('reset')">Reset filters</UiButton>
                    <slot name="browse-all" />
                </div>
            </template>
        </UiEmptyState>

        <template v-else>
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                <li v-for="card in cards" :key="card.ulid" class="flex">
                    <GameBaseCard class="w-full" :card="card" :category-label="labels[card.category]" />
                </li>
            </ul>
            <div v-if="nextCursor" class="flex justify-center">
                <UiButton variant="secondary" :loading="loadingMore" @click="loadMore">Load more</UiButton>
            </div>
        </template>
    </div>
</template>
