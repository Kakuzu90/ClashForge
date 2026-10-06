<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import GameClanChip from '@/Components/game/GameClanChip.vue';
import GameFeaturedBadge from '@/Components/game/GameFeaturedBadge.vue';
import GameThBadge from '@/Components/game/GameThBadge.vue';
import GameVerifiedBadge from '@/Components/game/GameVerifiedBadge.vue';
import GameXpBadge from '@/Components/game/GameXpBadge.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { formatDuration } from '@/Composables/useDateTime';
import { formatCount } from '@/Composables/useNumberFormat';
import { show } from '@/routes/accounts';
import { Link } from '@inertiajs/vue3';
import { computed, useId } from 'vue';

export type PlayerCardVariant = 'hero' | 'standard' | 'compact';

// PlayerCard (specs/18 §4): a CoC account as a collectible card. `card: null` is the loading
// skeleton. Unverified and stale cards mute our own chrome and text only: game assets are never
// filtered or faded (specs/18 §2.1). `hero` is the page header; the others link to the page.
// `standard` is the list card (P2-22): XP level instead of the Town Hall, no stats or clan, one line
// per field so every card in a list is the same height. The `actions` slot sits above the card's
// link overlay.
const props = withDefaults(defineProps<{ card: App.Domain.PlayerAccounts.Data.PlayerCardData | null; variant?: PlayerCardVariant }>(), {
    variant: 'standard',
});

const headingId = useId();
const verified = computed(() => props.card?.status === 'verified');
const disputed = computed(() => props.card?.status === 'disputed');
const muted = computed(() => props.card !== null && (props.card.stale || props.card.status === 'unverified' || props.card.status === 'suspended'));
const age = computed(() => (props.card?.syncedAgeSeconds == null ? null : formatDuration(props.card.syncedAgeSeconds)));
const syncLine = computed(() => {
    if (props.card === null) return '';
    if (age.value === null) return 'Not synced yet';

    return props.card.stale ? `Data from ${age.value} ago` : `Updated ${age.value} ago`;
});
const facts = computed(() =>
    props.card === null
        ? []
        : [
              { key: 'trophies', label: 'Trophies', value: props.card.trophies },
              { key: 'warStars', label: 'War stars', value: props.card.warStars },
              { key: 'xp', label: 'XP level', value: props.card.xpLevel },
          ],
);
</script>

<template>
    <div v-if="card === null" class="rounded-lg border border-line bg-surface p-4" :class="variant === 'standard' ? 'h-full' : ''">
        <UiSkeleton variant="card" label="Loading account" />
    </div>

    <article
        v-else
        class="relative overflow-hidden rounded-lg border-2 bg-surface"
        :class="[
            muted ? 'border-line' : verified ? 'border-line-strong border-b-brand-shadow' : 'border-line-strong',
            variant === 'hero' ? 'p-4 sm:p-6' : variant === 'standard' ? 'p-4' : 'px-3 py-2',
            disputed && variant !== 'standard' ? (variant === 'compact' ? 'pt-8' : 'pt-10') : '',
            variant === 'standard' ? 'h-full' : '',
            variant !== 'hero'
                ? 'transition-transform duration-150 hover:-translate-y-0.5 motion-reduce:transition-none motion-reduce:hover:translate-y-0'
                : '',
        ]"
        :aria-labelledby="headingId"
    >
        <p v-if="disputed && variant !== 'standard'" class="absolute inset-x-0 top-0 bg-warning/20 px-4 py-1 text-xs text-fg uppercase">
            Under review
        </p>

        <div class="flex items-start gap-3" :class="variant === 'hero' ? '' : 'items-center'">
            <GameXpBadge v-if="variant === 'standard'" :level="card.xpLevel" size="md" />
            <GameThBadge
                v-else-if="card.townHallLevel !== null"
                :level="card.townHallLevel"
                :asset="card.townHall"
                :size="variant === 'hero' ? 'lg' : 'sm'"
            />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-x-2 gap-y-1" :class="variant === 'standard' ? 'flex-nowrap' : 'flex-wrap'">
                    <component
                        :is="variant === 'hero' ? 'h1' : 'h3'"
                        :id="headingId"
                        :class="[
                            variant === 'hero' ? 'font-display text-h1 break-words' : 'text-body font-semibold',
                            variant === 'standard' ? 'min-w-0 truncate' : 'break-words',
                            muted ? 'text-fg-secondary' : 'text-fg',
                        ]"
                    >
                        <Link
                            v-if="variant !== 'hero'"
                            :href="show(card.ulid).url"
                            class="outline-none after:absolute after:inset-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            {{ card.name }}
                        </Link>
                        <template v-else>{{ card.name }}</template>
                    </component>
                    <GameVerifiedBadge v-if="verified" :size="variant === 'hero' ? 24 : 16" />
                    <GameFeaturedBadge v-if="card.featured" :size="variant === 'hero' ? 24 : 16" />
                    <UiPill
                        v-if="card.status === 'unverified' || card.status === 'suspended' || (disputed && variant === 'standard')"
                        :label="card.statusLabel"
                        :tone="card.status === 'suspended' ? 'danger' : disputed ? 'warning' : 'neutral'"
                        class="shrink-0"
                    />
                </div>
                <p class="font-mono text-tag text-fg-secondary">{{ card.tag }}</p>
                <p v-if="variant !== 'hero'" class="truncate text-xs" :class="card.stale ? 'text-warning' : 'text-fg-muted'">{{ syncLine }}</p>
                <div v-if="$slots.actions" class="relative z-10 mt-1">
                    <slot name="actions" />
                </div>
            </div>

            <span v-if="card.league && variant !== 'compact'" class="hidden shrink-0 flex-col items-center gap-1 text-center sm:flex">
                <GameAsset :asset="card.league" :size="variant === 'hero' ? 64 : 48" :lazy="variant !== 'hero'" />
                <span class="text-xs whitespace-nowrap text-fg-secondary">{{ card.leagueName }}</span>
            </span>
        </div>

        <template v-if="variant === 'hero'">
            <dl class="mt-4 grid grid-cols-3 gap-3">
                <div v-for="fact in facts" :key="fact.key" class="flex flex-col">
                    <dt class="order-last text-xs text-fg-secondary uppercase">{{ fact.label }}</dt>
                    <dd class="font-display text-h3 tabular-nums" :class="fact.value === null ? 'font-body text-sm text-fg-muted' : 'text-fg'">
                        {{ fact.value === null ? 'Not available' : formatCount(fact.value, 'en') }}
                    </dd>
                </div>
            </dl>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <GameClanChip v-if="card.clan" :clan="card.clan" />
                <span v-else class="text-sm text-fg-muted">{{ card.clanHidden ? 'Clan not shared' : 'No clan' }}</span>
                <span v-if="card.league" class="text-xs whitespace-nowrap text-fg-secondary sm:hidden">{{ card.leagueName }}</span>
                <p class="text-xs" :class="card.stale ? 'text-warning' : 'text-fg-muted'">{{ syncLine }}</p>
            </div>
        </template>
    </article>
</template>
