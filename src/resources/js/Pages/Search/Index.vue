<script setup lang="ts">
import BaseFeedFilters from '@/Components/bases/BaseFeedFilters.vue';
import BaseFeedGrid from '@/Components/bases/BaseFeedGrid.vue';
import ThChipRow from '@/Components/bases/ThChipRow.vue';
import { activeFilterCount, withFilters } from '@/Components/bases/feedQuery';
import GameBaseCard from '@/Components/game/GameBaseCard.vue';
import GamePlayerCard from '@/Components/game/GamePlayerCard.vue';
import SearchPlayerRow from '@/Components/search/SearchPlayerRow.vue';
import { facetCounts, searchQuery, searchTabs, withoutMatch, type SearchTab } from '@/Components/search/searchQuery';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import UiTabs from '@/Components/ui/UiTabs.vue';
import { useNavigating } from '@/Composables/useNavigating';
import { usePageProps } from '@/Composables/usePageProps';
import { useVisitError } from '@/Composables/useVisitError';
import AppLayout from '@/Layouts/AppLayout.vue';
import { search } from '@/routes';
import { attach } from '@/routes/accounts';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

type Filters = App.Domain.Bases.Data.FeedFiltersData;
type Kind = Exclude<SearchTab, 'all'>;

// `/search` (FR-SEARCH-1..3): one input, then everything grouped or one kind per tab. Filters read
// from the text show as chips that remove their words; on the Bases tab the `/bases` filters apply
// with facet counts. The state lives in the URL.
const props = defineProps<{
    q: string;
    type: SearchTab;
    filters: Filters;
    parsed: App.Domain.Search.Data.ParsedFilterData[];
    tag: string | null;
    searched: Kind[];
    more: Record<Kind, boolean>;
    options: App.Domain.Bases.Data.FeedOptionsData;
    facets: App.Domain.Search.Data.SearchFacetsData | null;
    bases: App.Domain.Bases.Data.BaseCardData[];
    players: App.Domain.Users.Data.PlayerHitData[];
    accounts: App.Domain.PlayerAccounts.Data.PlayerCardData[];
    nextCursor: string | null;
}>();

const { auth, url } = usePageProps();
const page = usePage();
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string>);
const text = ref(props.q);
watch(
    () => props.q,
    (q) => (text.value = q),
);

const sheet = ref(false);
const loadingMore = ref(false);
const error = useVisitError();
const navigating = useNavigating((target) => target.pathname === search().url);

const tab = computed({
    get: () => props.type,
    set: (value: string) => visit(searchQuery(props.q, value as SearchTab)),
});
const empty = computed(() => props.bases.length + props.players.length + props.accounts.length === 0);
const filterCount = computed(() => activeFilterCount(props.filters));
const thCounts = computed(() => facetCounts(props.facets?.thLevels));
const categoryCounts = computed(() => facetCounts(props.facets?.categories));
const categoryLabels = computed(() => Object.fromEntries(props.options.categories.map((c) => [c.value, c.label])));
// The query this page was loaded with, so "Load more" asks for the same search.
const pageQuery = computed(() => {
    const params = new URL(url.value, 'http://local').searchParams;
    params.delete('cursor');
    return Object.fromEntries(params.entries());
});

function visit(query: Record<string, string>) {
    sheet.value = false;
    router.get(search().url, query, { preserveScroll: false });
}

function submit() {
    visit(searchQuery(text.value.trim(), props.type === 'all' ? 'all' : props.type));
}

function removeChip(chip: App.Domain.Search.Data.ParsedFilterData) {
    if (props.type === 'bases') {
        const patch: Partial<Filters> = chip.key === 'th' ? { thMin: null, thMax: null } : { category: null };
        visit(searchQuery(props.q, 'bases', withFilters(props.filters, patch), props.parsed));
    } else {
        visit(searchQuery(withoutMatch(props.q, chip.match), props.type));
    }
}

const applyFilters = (patch: Partial<Filters>) => visit(searchQuery(props.q, 'bases', withFilters(props.filters, patch), props.parsed));
const resetFilters = () =>
    visit(
        searchQuery(
            props.q,
            'bases',
            { thMin: null, thMax: null, category: null, tag: null, minLikes: null, hasVideo: false, sort: 'relevance' },
            props.parsed,
        ),
    );

function loadMore(kind: Kind) {
    if (!props.nextCursor) return;
    loadingMore.value = true;
    router.reload({
        only: [kind, 'nextCursor'],
        data: { ...pageQuery.value, cursor: props.nextCursor },
        preserveUrl: true,
        onFinish: () => (loadingMore.value = false),
    });
}

const retry = () => router.reload();
const examples = ['TH16 anti 3 star', 'hybrid farming', '#2PP'];
</script>

<template>
    <div class="flex flex-col gap-6 py-6">
        <div>
            <h1 class="font-display text-h1">Search</h1>
            <form class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end" role="search" @submit.prevent="submit">
                <UiInput
                    v-model="text"
                    class="flex-1"
                    type="search"
                    label="Search bases, players and accounts"
                    name="q"
                    :maxlength="100"
                    :error="errors.q"
                    autocomplete="off"
                    :autofocus="!q"
                />
                <UiButton type="submit">Search</UiButton>
            </form>
            <p v-if="errors.cursor" class="mt-2 text-sm text-danger">{{ errors.cursor }}</p>
        </div>

        <!-- A player tag with no account we may show. -->
        <UiEmptyState
            v-if="tag"
            :title="`No player with ${tag} on Clash Commons yet`"
            body="Only accounts their owners have attached and verified can be found."
        >
            <template v-if="auth?.user" #action>
                <UiButton variant="secondary" :href="attach().url">Attach your account</UiButton>
            </template>
        </UiEmptyState>

        <UiEmptyState v-else-if="!q" title="Find bases, players and accounts" body="Search by name, a player tag, or what the base is for.">
            <template #action>
                <ul class="flex flex-wrap justify-center gap-2" aria-label="Example searches">
                    <li v-for="example in examples" :key="example">
                        <UiButton variant="ghost" size="sm" @click="visit({ q: example })">{{ example }}</UiButton>
                    </li>
                </ul>
            </template>
        </UiEmptyState>

        <template v-else>
            <div v-if="parsed.length" class="flex flex-wrap items-center gap-2" aria-label="Filters read from your search" role="group">
                <span class="text-sm text-fg-secondary">Searching for</span>
                <UiPill v-for="chip in parsed" :key="chip.key" :label="chip.label" tone="brand" removable @remove="removeChip(chip)" />
            </div>

            <UiAlert v-if="error && !loadingMore" kind="danger" title="Search didn't load">
                <template v-if="error.throttled">Too many searches in a row. Wait a minute and try again.</template>
                <template v-else-if="error.requestId">
                    If it keeps failing, quote request id <span class="font-semibold">{{ error.requestId }}</span
                    >.
                </template>
                <template v-else>Check your connection and try again.</template>
                <UiButton class="mt-3" variant="secondary" size="sm" @click="retry">Try again</UiButton>
            </UiAlert>

            <UiTabs v-model="tab" :tabs="searchTabs" label="Result type">
                <template #all>
                    <div v-if="navigating" class="flex flex-col gap-4 pt-6" aria-busy="true">
                        <span class="sr-only" role="status">Searching</span>
                        <UiSkeleton v-for="n in 4" :key="n" variant="card" />
                    </div>
                    <UiEmptyState v-else-if="empty" :title="`Nothing found for “${q}”`" body="Check the spelling, or try fewer words." />
                    <div v-else class="flex flex-col gap-8 pt-6">
                        <section v-if="bases.length" aria-labelledby="results-bases">
                            <div class="mb-3 flex items-baseline justify-between gap-3">
                                <h2 id="results-bases" class="font-display text-h3">Bases</h2>
                                <UiButton v-if="more.bases" variant="ghost" size="sm" @click="tab = 'bases'">See all bases</UiButton>
                            </div>
                            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <li v-for="card in bases" :key="card.ulid" class="flex">
                                    <GameBaseCard class="w-full" :card="card" :category-label="categoryLabels[card.category]" />
                                </li>
                            </ul>
                        </section>
                        <section v-if="players.length" aria-labelledby="results-players">
                            <div class="mb-3 flex items-baseline justify-between gap-3">
                                <h2 id="results-players" class="font-display text-h3">Players</h2>
                                <UiButton v-if="more.players" variant="ghost" size="sm" @click="tab = 'players'">See all players</UiButton>
                            </div>
                            <ul class="grid gap-2 md:grid-cols-2">
                                <li v-for="player in players" :key="player.username"><SearchPlayerRow :player="player" /></li>
                            </ul>
                        </section>
                        <section v-if="accounts.length" aria-labelledby="results-accounts">
                            <div class="mb-3 flex items-baseline justify-between gap-3">
                                <h2 id="results-accounts" class="font-display text-h3">Accounts</h2>
                                <UiButton v-if="more.accounts" variant="ghost" size="sm" @click="tab = 'accounts'">See all accounts</UiButton>
                            </div>
                            <ul class="grid gap-4 md:grid-cols-2">
                                <li v-for="account in accounts" :key="account.ulid"><GamePlayerCard :card="account" /></li>
                            </ul>
                        </section>
                    </div>
                </template>

                <template #bases>
                    <div class="flex flex-col gap-4 pt-6">
                        <UiButton class="self-start lg:hidden" variant="secondary" @click="sheet = true">
                            {{ filterCount ? `Filters (${filterCount})` : 'Filters' }}
                        </UiButton>
                        <div class="grid gap-6 lg:grid-cols-[16rem_1fr] lg:items-start">
                            <aside class="hidden lg:sticky lg:top-20 lg:block" aria-label="Filters">
                                <div class="rounded-lg border border-line bg-surface p-4">
                                    <BaseFeedFilters
                                        :filters="filters"
                                        :options="options"
                                        :category-counts="categoryCounts"
                                        instant
                                        @apply="applyFilters"
                                        @reset="resetFilters"
                                    />
                                </div>
                            </aside>
                            <div class="flex min-w-0 flex-col gap-4">
                                <ThChipRow
                                    :th-min="options.thMin"
                                    :th-max="options.thMax"
                                    :selected-min="filters.thMin"
                                    :selected-max="filters.thMax"
                                    :from-account="false"
                                    :counts="thCounts"
                                    @select="(range) => applyFilters({ thMin: range?.[0] ?? null, thMax: range?.[1] ?? null })"
                                />
                                <BaseFeedGrid
                                    :cards="bases"
                                    :next-cursor="nextCursor"
                                    :query="pageQuery"
                                    :categories="options.categories"
                                    :loading="navigating"
                                    :error="null"
                                    :filtered="filterCount > 0"
                                    prop="bases"
                                    @reset="resetFilters"
                                    @retry="retry"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <template v-for="kind in ['players', 'accounts'] as const" :key="kind" #[kind]>
                    <div class="flex flex-col gap-4 pt-6">
                        <div v-if="navigating" class="flex flex-col gap-2" aria-busy="true">
                            <span class="sr-only" role="status">Searching</span>
                            <UiSkeleton v-for="n in 5" :key="n" variant="avatar" />
                        </div>
                        <UiEmptyState
                            v-else-if="!searched.includes(kind)"
                            :title="kind === 'players' ? 'Add a name to search players' : 'Add a name to search accounts'"
                            body="Town Hall levels and categories only narrow down bases."
                        />
                        <UiEmptyState
                            v-else-if="(kind === 'players' ? players : accounts).length === 0"
                            :title="kind === 'players' ? `No players match “${q}”` : `No accounts match “${q}”`"
                            :body="
                                kind === 'players'
                                    ? 'Private profiles and players who turned search off are not listed.'
                                    : 'Only verified accounts their owners show publicly are listed.'
                            "
                        />
                        <template v-else>
                            <ul class="grid md:grid-cols-2" :class="kind === 'players' ? 'gap-2' : 'gap-4'">
                                <template v-if="kind === 'players'">
                                    <li v-for="player in players" :key="player.username"><SearchPlayerRow :player="player" /></li>
                                </template>
                                <template v-else>
                                    <li v-for="account in accounts" :key="account.ulid"><GamePlayerCard :card="account" /></li>
                                </template>
                            </ul>
                            <div v-if="nextCursor" class="flex justify-center">
                                <UiButton variant="secondary" :loading="loadingMore" @click="loadMore(kind)">Load more</UiButton>
                            </div>
                        </template>
                    </div>
                </template>
            </UiTabs>
        </template>

        <UiModal v-model:open="sheet" title="Filters">
            <BaseFeedFilters :filters="filters" :options="options" :category-counts="categoryCounts" @apply="applyFilters" @reset="resetFilters" />
        </UiModal>
    </div>
</template>
