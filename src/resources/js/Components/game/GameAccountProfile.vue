<script setup lang="ts">
import GameAsset from '@/Components/game/GameAsset.vue';
import GameFeaturedBadge from '@/Components/game/GameFeaturedBadge.vue';
import GameVerifiedBadge from '@/Components/game/GameVerifiedBadge.vue';
import GameXpBadge from '@/Components/game/GameXpBadge.vue';
import UiPill from '@/Components/ui/UiPill.vue';
import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import { show } from '@/routes/accounts';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// The profile panel on the account page (specs/18 §6), laid out as the game's own profile: who
// the player is, their clan and war stars, their league and trophies, then the donation strip. Both
// village tabs use it; only the ranked column changes (`ranked`: the Builder Base league and
// trophies). Our chrome only; the game assets are the league emblem and clan badge. A user
// profile shows it as the featured account (P2-22): no `stats` (values come from the card, without
// deltas), no donation strip, and the name links to the account page.
export interface RankedColumn {
    league: App.Domain.GameAssets.Data.GameAssetData | null;
    leagueName: string | null;
    trophies: string;
    best: string;
}

const props = withDefaults(
    defineProps<{
        card: App.Domain.PlayerAccounts.Data.PlayerCardData;
        stats?: App.Domain.PlayerAccounts.Data.AccountStatData[];
        deltaLabel?: string;
        ranked?: RankedColumn;
        donations?: boolean;
        linked?: boolean;
        label?: string;
    }>(),
    { stats: undefined, deltaLabel: '', ranked: undefined, donations: true, linked: false, label: 'Profile' },
);

const rank = computed<RankedColumn>(
    () => props.ranked ?? { league: props.card.league, leagueName: props.card.leagueName, trophies: 'trophies', best: 'best_trophies' },
);

const fromCard: Record<string, number | null> = {
    war_stars: props.card.warStars,
    trophies: props.card.trophies,
    best_trophies: props.card.bestTrophies,
};
const stat = (key: string) =>
    props.stats?.find((s) => s.key === key) ?? { key, label: key, value: props.stats ? null : (fromCard[key] ?? null), delta: null };
const verified = computed(() => props.card.status === 'verified');
const clanLine = computed(() => (props.card.clanHidden ? 'Clan not shared' : 'No clan'));
</script>

<template>
    <section :aria-label="label" class="overflow-hidden rounded-lg border-2 border-line-strong border-b-brand-shadow bg-surface">
        <div class="grid divide-y divide-line lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1.2fr)] lg:divide-x lg:divide-y-0">
            <div class="flex items-start gap-3 p-4 sm:p-6">
                <GameXpBadge :level="card.xpLevel" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <p class="font-display text-h1 break-words text-fg">
                            <Link
                                v-if="linked"
                                :href="show(card.ulid).url"
                                class="underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            >
                                {{ card.name }}
                            </Link>
                            <template v-else>{{ card.name }}</template>
                        </p>
                        <GameVerifiedBadge v-if="verified" :size="24" />
                        <GameFeaturedBadge v-if="card.featured" :size="24" />
                        <UiPill
                            v-if="card.status === 'unverified' || card.status === 'suspended'"
                            :label="card.statusLabel"
                            :tone="card.status === 'suspended' ? 'danger' : 'neutral'"
                        />
                    </div>
                    <p class="font-mono text-tag text-fg-secondary">{{ card.tag }}</p>
                    <p v-if="card.clan?.roleLabel" class="mt-1 text-sm font-semibold text-fg">{{ card.clan.roleLabel }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 sm:p-6 lg:flex-col lg:text-center">
                <GameAsset v-if="card.clan" :asset="card.clan.badge" :size="64" :lazy="false" />
                <div class="flex min-w-0 flex-1 flex-col gap-3 lg:items-center">
                    <div class="min-w-0">
                        <p v-if="card.clan" class="truncate font-display text-h3 text-fg">{{ card.clan.name }}</p>
                        <p v-else class="text-sm text-fg-muted">{{ clanLine }}</p>
                        <p v-if="card.clan?.level" class="text-xs text-fg-secondary">Clan level {{ card.clan.level }}</p>
                    </div>
                    <dl>
                        <UiStatBlock
                            label="War stars won"
                            :value="stat('war_stars').value"
                            :delta="stat('war_stars').delta"
                            :delta-label="deltaLabel"
                        />
                    </dl>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 sm:p-6">
                <GameAsset v-if="rank.league" :asset="rank.league" :size="64" :lazy="false" />
                <div class="flex min-w-0 flex-1 flex-col gap-3">
                    <div>
                        <p class="text-xs text-fg-secondary uppercase">Current league</p>
                        <p class="font-display text-h3 whitespace-nowrap text-fg">{{ rank.leagueName ?? 'Unranked' }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-3">
                        <UiStatBlock
                            label="Trophies"
                            :value="stat(rank.trophies).value"
                            :delta="stat(rank.trophies).delta"
                            :delta-label="deltaLabel"
                        />
                        <UiStatBlock label="All-time best" :value="stat(rank.best).value" />
                    </dl>
                </div>
            </div>
        </div>

        <dl v-if="donations" class="grid grid-cols-2 gap-3 border-t border-line bg-surface-raised px-4 py-3 sm:px-6">
            <UiStatBlock label="Troops donated" :value="stat('donations').value" />
            <UiStatBlock label="Troops received" :value="stat('donations_received').value" />
        </dl>
    </section>
</template>
