<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiSelect from '@/Components/ui/UiSelect.vue';
import UiToggle from '@/Components/ui/UiToggle.vue';
import { nextTick, ref, watch } from 'vue';

type Filters = App.Domain.Bases.Data.FeedFiltersData;

// The `/bases` filter form (FR-BASE-13): category, tag, minimum likes, video and sort. In the
// desktop panel (`instant`) each change applies at once, text fields when they lose focus or on
// Enter, so the panel needs no button at its foot; in the phone sheet they apply together. The page
// decides where it sits (specs/18 §5).
const props = defineProps<{ filters: Filters; options: App.Domain.Bases.Data.FeedOptionsData; instant?: boolean }>();

const emit = defineEmits<{ apply: [patch: Partial<Filters>]; reset: [] }>();

const category = ref<string | null>(null);
const tag = ref('');
const minLikes = ref('');
const video = ref(false);
const sort = ref<string | null>(null);

function load(filters: Filters) {
    category.value = filters.category;
    tag.value = filters.tag ?? '';
    minLikes.value = filters.minLikes === null ? '' : String(filters.minLikes);
    video.value = filters.hasVideo;
    sort.value = filters.sort;
}

watch(() => props.filters, load, { immediate: true });

// Applies after Vue has written the new value to the field's ref.
function changed() {
    if (props.instant) nextTick(apply);
}

function apply() {
    const likes = Number.parseInt(minLikes.value, 10);

    emit('apply', {
        category: (category.value as Filters['category']) || null,
        tag: tag.value.trim() || null,
        minLikes: Number.isFinite(likes) && likes > 0 ? likes : null,
        hasVideo: video.value,
        sort: (sort.value as Filters['sort']) ?? 'trending',
    });
}
</script>

<template>
    <form class="flex flex-col gap-3" @submit.prevent="apply">
        <div v-if="instant" class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-fg">Filters</h2>
            <UiButton variant="ghost" size="sm" @click="emit('reset')">Reset</UiButton>
        </div>
        <UiSelect v-model="sort" label="Sort by" searchable :options="options.sorts" @update:model-value="changed" />
        <UiSelect
            v-model="category"
            label="Category"
            searchable
            placeholder="Any category"
            :options="[{ value: '', label: 'Any category' }, ...options.categories]"
            @update:model-value="changed"
        />
        <div class="flex flex-col gap-2" @change="changed">
            <UiInput v-model="tag" label="Tag" />
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Suggested tags">
                <UiPill
                    v-for="suggestion in options.suggestedTags"
                    :key="suggestion"
                    :label="suggestion"
                    selectable
                    :selected="tag === suggestion"
                    @toggle="
                        tag = tag === suggestion ? '' : suggestion;
                        changed();
                    "
                />
            </div>
        </div>
        <div @change="changed">
            <UiInput v-model="minLikes" type="number" label="At least this many likes" />
        </div>
        <UiToggle v-model="video" label="With a replay video" @update:model-value="changed" />
        <div v-if="!instant" class="flex flex-col gap-2 sm:flex-row">
            <UiButton type="submit">Apply filters</UiButton>
            <UiButton variant="ghost" @click="emit('reset')">Reset</UiButton>
        </div>
    </form>
</template>
