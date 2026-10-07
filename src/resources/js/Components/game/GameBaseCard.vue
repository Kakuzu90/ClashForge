<script setup lang="ts">
import GamePlayerMini from '@/Components/game/GamePlayerMini.vue';
import GameThBadge from '@/Components/game/GameThBadge.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiResourceCounter from '@/Components/ui/UiResourceCounter.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { thTier } from '@/Composables/useThTier';
import { useId } from 'vue';

// BaseCard (specs/18 §4): screenshot-led. `card: null` is the loading skeleton. Without a screenshot
// or poster the cover is a generated tier gradient with the Town Hall numeral. The card links to the
// base page once it ships (P3-11); until then only the author and account inside it are links.
defineProps<{ card: App.Domain.Bases.Data.BaseCardData | null; categoryLabel?: string }>();

const headingId = useId();

// Static class strings, so Tailwind sees every tier colour.
const fallback: Record<number, string> = {
    1: 'from-th-1/40',
    2: 'from-th-2/40',
    3: 'from-th-3/40',
    4: 'from-th-4/40',
    5: 'from-th-5/40',
    6: 'from-th-6/40',
    7: 'from-th-7/40',
};
</script>

<template>
    <div v-if="card === null" class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-3">
        <UiSkeleton variant="media" />
        <UiSkeleton :lines="2" />
    </div>

    <article
        v-else
        class="group relative flex flex-col overflow-hidden rounded-lg border-2 border-line-strong bg-surface transition-transform duration-150 hover:-translate-y-0.5 hover:border-brand/60 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
        :aria-labelledby="headingId"
    >
        <div class="relative aspect-video overflow-hidden bg-surface-raised">
            <img
                v-if="card.cover"
                :src="card.cover.url"
                :width="card.cover.width"
                :height="card.cover.height"
                alt=""
                loading="lazy"
                decoding="async"
                class="size-full object-cover"
            />
            <div
                v-else
                class="flex size-full items-center justify-center bg-gradient-to-br to-surface-raised"
                :class="fallback[thTier(card.thLevel)]"
                aria-hidden="true"
            >
                <span class="font-display text-display text-fg/40">{{ card.thLevel }}</span>
            </div>
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-scrim/60 via-transparent to-transparent" aria-hidden="true" />
            <GameThBadge class="absolute top-2 left-2" :level="card.thLevel" size="md" />
            <UiPill class="absolute top-2 right-2" :label="categoryLabel ?? card.category" />
            <span
                v-if="card.hasVideo"
                class="absolute right-2 bottom-2 inline-flex size-7 items-center justify-center rounded-sm bg-scrim text-fg"
                role="img"
                aria-label="Has a replay video"
            >
                <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true"><path d="M4 2.5v9l7-4.5z" fill="currentColor" /></svg>
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-3">
            <h3 :id="headingId" class="line-clamp-2 min-h-12 font-semibold text-fg">{{ card.title }}</h3>
            <GamePlayerMini :author="card.author" :credit="card.credit" />
            <div class="mt-auto flex items-center gap-4 pt-1">
                <UiResourceCounter kind="likes" :count="card.likes" />
                <UiResourceCounter kind="copies" :count="card.copies" />
                <UiResourceCounter kind="views" :count="card.views" />
            </div>
        </div>
    </article>
</template>
