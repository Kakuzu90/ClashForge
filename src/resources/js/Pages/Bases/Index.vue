<script setup lang="ts">
import BaseFeedFilters from '@/Components/bases/BaseFeedFilters.vue';
import BaseFeedGrid from '@/Components/bases/BaseFeedGrid.vue';
import ThChipRow from '@/Components/bases/ThChipRow.vue';
import { activeFilterCount, feedQuery, withFilters } from '@/Components/bases/feedQuery';
import UiButton from '@/Components/ui/UiButton.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import { useNavigating } from '@/Composables/useNavigating';
import { useVisitError } from '@/Composables/useVisitError';
import AppLayout from '@/Layouts/AppLayout.vue';
import { index } from '@/routes/bases';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

type Filters = App.Domain.Bases.Data.FeedFiltersData;

// `/bases` (FR-BASE-13): every filter and sort. The filter state lives in the URL, so a filtered
// feed can be shared and the back button walks through it.
const props = defineProps<{
    filters: Filters;
    options: App.Domain.Bases.Data.FeedOptionsData;
    cards: App.Domain.Bases.Data.BaseCardData[];
    nextCursor: string | null;
}>();

const sheet = ref(false);
const error = useVisitError();
const navigating = useNavigating((url) => url.pathname === index().url);
const count = computed(() => activeFilterCount(props.filters));

// Every filter change starts the list from the top.
function visit(filters: Filters) {
    sheet.value = false;
    router.get(index().url, feedQuery(filters), { preserveScroll: false });
}

const apply = (patch: Partial<Filters>) => visit(withFilters(props.filters, patch));
const reset = () => visit({ thMin: null, thMax: null, category: null, tag: null, minLikes: null, hasVideo: false, sort: 'trending' });
const retry = () => router.reload();
</script>

<template>
    <div class="flex flex-col gap-6 py-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-display text-h1">Base layouts</h1>
                <p class="mt-1 text-body text-fg-secondary">Layouts from verified players, ready to copy into the game.</p>
            </div>
            <UiButton class="lg:hidden" variant="secondary" @click="sheet = true">{{ count ? `Filters (${count})` : 'Filters' }}</UiButton>
        </div>

        <!-- The filter panel and the Town Hall chips stay put; only the cards move. -->
        <div class="grid gap-6 lg:grid-cols-[16rem_1fr] lg:items-start">
            <aside class="hidden lg:sticky lg:top-20 lg:block" aria-label="Filters">
                <div class="rounded-lg border border-line bg-surface p-4">
                    <BaseFeedFilters :filters="filters" :options="options" instant @apply="apply" @reset="reset" />
                </div>
            </aside>
            <div class="flex min-w-0 flex-col gap-4">
                <div class="sticky top-14 z-20 -mx-4 bg-page/95 px-4 backdrop-blur-sm lg:mx-0 lg:px-0">
                    <ThChipRow
                        :th-min="options.thMin"
                        :th-max="options.thMax"
                        :selected-min="filters.thMin"
                        :selected-max="filters.thMax"
                        :from-account="false"
                        @select="(range) => apply({ thMin: range?.[0] ?? null, thMax: range?.[1] ?? null })"
                    />
                </div>
                <BaseFeedGrid
                    :cards="cards"
                    :next-cursor="nextCursor"
                    :query="feedQuery(filters)"
                    :categories="options.categories"
                    :loading="navigating"
                    :error="error"
                    :filtered="count > 0"
                    @reset="reset"
                    @retry="retry"
                />
            </div>
        </div>

        <UiModal v-model:open="sheet" title="Filters">
            <BaseFeedFilters :filters="filters" :options="options" @apply="apply" @reset="reset" />
        </UiModal>
    </div>
</template>
