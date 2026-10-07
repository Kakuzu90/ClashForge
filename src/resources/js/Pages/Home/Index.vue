<script setup lang="ts">
import BaseFeedGrid from '@/Components/bases/BaseFeedGrid.vue';
import ThChipRow from '@/Components/bases/ThChipRow.vue';
import { feedQuery, withFilters } from '@/Components/bases/feedQuery';
import UiButton from '@/Components/ui/UiButton.vue';
import UiTabs from '@/Components/ui/UiTabs.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { useNavigating } from '@/Composables/useNavigating';
import { useVisitError } from '@/Composables/useVisitError';
import AppLayout from '@/Layouts/AppLayout.vue';
import { home, register } from '@/routes';
import { index as bases } from '@/routes/bases';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

type Filters = App.Domain.Bases.Data.FeedFiltersData;

// Home feed (specs/18 §6): hero, the Town Hall chip row (sticky on scroll), the sort tabs and the
// card grid. Signed in, the feed starts at the featured account's Town Hall ±1 (specs/17 §6).
const props = defineProps<{
    filters: Filters;
    thFromAccount: boolean;
    options: App.Domain.Bases.Data.FeedOptionsData;
    cards: App.Domain.Bases.Data.BaseCardData[];
    nextCursor: string | null;
}>();

const { auth } = usePageProps();
const guest = computed(() => !auth.value?.user);
const error = useVisitError();
const navigating = useNavigating((url) => url.pathname === home().url);
const tabs = computed(() => props.options.sorts.map((sort) => ({ key: sort.value, label: sort.label })));

// The query that reproduces this feed exactly: every Town Hall is asked for as `th=all`, since no
// `th` at all means the signed-in default. "Load more" sends it with the cursor.
const query = computed(() => feedQuery(props.filters, { allTh: props.filters.thMin === null }));

function visit(filters: Filters, keepDefault = false) {
    const next = feedQuery(filters, { allTh: filters.thMin === null });
    // A sort change on the default Town Hall leaves `th` out, so the server keeps it as the default.
    // Every change starts the list from the top.
    if (keepDefault) delete next.th;
    router.get(home().url, next, { preserveScroll: false });
}

const sort = computed({
    get: () => props.filters.sort,
    set: (value: string) => visit(withFilters(props.filters, { sort: value as Filters['sort'] }), props.thFromAccount),
});

const selectTh = (range: [number, number] | null) => visit(withFilters(props.filters, { thMin: range?.[0] ?? null, thMax: range?.[1] ?? null }));
const reset = () => visit(withFilters(props.filters, { thMin: null, thMax: null }));
const retry = () => router.reload();
</script>

<template>
    <div class="flex flex-col gap-6 pb-10">
        <section class="pt-10 md:pt-16">
            <h1 class="font-display text-display">Clash Commons</h1>
            <p class="mt-3 max-w-xl text-body text-fg-secondary">
                Base layouts, verified player cards and clan recruitment for Clash of Clans players.
            </p>
            <UiButton v-if="guest" class="mt-6" :href="register().url">Create your account</UiButton>
        </section>

        <div class="sticky top-14 z-20 -mx-4 bg-page/95 px-4 py-2 backdrop-blur-sm">
            <ThChipRow
                :th-min="options.thMin"
                :th-max="options.thMax"
                :selected-min="filters.thMin"
                :selected-max="filters.thMax"
                :from-account="thFromAccount"
                @select="selectTh"
            />
        </div>

        <UiTabs v-model="sort" :tabs="tabs" label="Sort bases">
            <template v-for="tab in tabs" :key="tab.key" #[tab.key]>
                <BaseFeedGrid
                    v-if="tab.key === filters.sort"
                    :cards="cards"
                    :next-cursor="nextCursor"
                    :query="query"
                    :categories="options.categories"
                    :loading="navigating"
                    :error="error"
                    :filtered="filters.thMin !== null"
                    @reset="reset"
                    @retry="retry"
                >
                    <template #browse-all>
                        <Link :href="bases().url" class="text-sm text-brand hover:underline">Browse all bases</Link>
                    </template>
                </BaseFeedGrid>
            </template>
        </UiTabs>
    </div>
</template>
