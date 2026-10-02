<script setup lang="ts">
import ProfileSkeleton from '@/Components/profile/ProfileSkeleton.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiBadge from '@/Components/ui/UiBadge.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiPill, { type PillTone } from '@/Components/ui/UiPill.vue';
import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import UiTabs, { type TabItem } from '@/Components/ui/UiTabs.vue';
import { formatMonthYear } from '@/Composables/useDateTime';
import { useNavigating } from '@/Composables/useNavigating';
import AppLayout from '@/Layouts/AppLayout.vue';
import { attach, verify } from '@/routes/accounts';
import { show } from '@/routes/profile';
import { edit as privacySettings } from '@/routes/settings/privacy';
import { edit as profileSettings } from '@/routes/settings/profile';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Profile.ProfileShowPageData;
const props = defineProps<{ profile: Props['profile']; ownAccounts?: Props['ownAccounts'] }>();

const name = computed(() => props.profile.displayName ?? props.profile.username);
const memberSince = computed(() => formatMonthYear(props.profile.memberSince));
const languages = computed(() => props.profile.languages.map((language) => language.label).join(', '));
const stats = computed(() => [
    { key: 'bases', label: 'Bases', value: props.profile.stats.basesPublished },
    { key: 'likes', label: 'Likes received', value: props.profile.stats.likesReceived },
    { key: 'copies', label: 'Copies', value: props.profile.stats.copies },
]);

// specs/18 §6: Accounts · Bases. Activity (P2) and Bookmarks (own only, P3-04) join with their features.
const tabs: TabItem[] = [
    { key: 'accounts', label: 'Accounts' },
    { key: 'bases', label: 'Bases' },
];
const tab = ref('accounts');

// The pill colour for a non-verified account; the words come from the server.
const statusTone: Record<App.Domain.PlayerAccounts.Enums.CocAccountStatus, PillTone> = {
    unverified: 'neutral',
    verified: 'success',
    disputed: 'warning',
    suspended: 'danger',
    released: 'neutral',
};

const profilePrefix = show.definition.url.split('{')[0] ?? '';
const navigating = useNavigating((url) => url.pathname.startsWith(profilePrefix));
</script>

<template>
    <ProfileSkeleton v-if="navigating" />
    <article v-else class="flex flex-col gap-6 py-6 md:py-8">
        <header
            class="flex flex-col gap-4 rounded-xl border border-b-[3px] border-line border-b-line-strong bg-surface p-4 sm:flex-row sm:items-center sm:p-6"
        >
            <UiAvatar :name="name" :src="profile.avatarUrl128" :size="96" />
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <h1 class="font-display text-h1 break-words text-fg">{{ name }}</h1>
                <p class="text-body text-fg-secondary">@{{ profile.username }}</p>
                <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-fg-secondary">
                    <span v-if="profile.country">{{ profile.country.label }}</span>
                    <span
                        >Member since <time :datetime="profile.memberSince">{{ memberSince }}</time></span
                    >
                    <span v-if="languages">Speaks {{ languages }}</span>
                </p>
            </div>
            <div v-if="profile.isOwn" class="flex flex-wrap gap-2 sm:self-start">
                <UiButton variant="secondary" size="sm" :href="profileSettings().url">Edit profile</UiButton>
                <UiButton variant="ghost" size="sm" :href="privacySettings().url">Privacy</UiButton>
            </div>
        </header>

        <section aria-labelledby="profile-stats">
            <h2 id="profile-stats" class="sr-only">Stats</h2>
            <dl class="grid grid-cols-3 gap-4 rounded-lg border border-line bg-surface p-4">
                <UiStatBlock v-for="stat in stats" :key="stat.key" :label="stat.label" :value="stat.value" />
            </dl>
        </section>

        <section v-if="profile.bio || profile.socials.length" aria-labelledby="profile-about" class="flex flex-col gap-3">
            <h2 id="profile-about" class="font-display text-h2 text-fg">About</h2>
            <p v-if="profile.bio" class="max-w-prose text-body whitespace-pre-line text-fg">{{ profile.bio }}</p>
            <ul v-if="profile.socials.length" class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                <li v-for="link in profile.socials" :key="link.network">
                    <a
                        v-if="link.url"
                        :href="link.url"
                        rel="nofollow ugc noopener"
                        class="inline-flex min-h-11 items-center font-semibold text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        {{ link.label }}: {{ link.handle }}
                    </a>
                    <span v-else class="inline-flex min-h-11 items-center text-fg-secondary">{{ link.label }}: {{ link.handle }}</span>
                </li>
            </ul>
        </section>

        <UiTabs v-model="tab" :tabs="tabs" label="Profile sections">
            <template #accounts>
                <template v-if="profile.isOwn">
                    <!-- A plain list until PlayerCards (P2-04); only the owner gets it. -->
                    <div v-if="ownAccounts && ownAccounts.length > 0" class="flex flex-col gap-4 py-4">
                        <ul class="flex flex-col divide-y divide-line-subtle rounded-lg border border-line-subtle bg-surface">
                            <li v-for="account in ownAccounts" :key="account.ulid" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold break-all text-fg">{{ account.name }}</p>
                                    <p class="font-mono text-sm text-fg-secondary">{{ account.tag }}</p>
                                </div>
                                <UiBadge v-if="account.status === 'verified'" kind="verified" />
                                <UiPill v-else :label="account.statusLabel" :tone="statusTone[account.status]" />
                                <UiBadge v-if="account.featured" kind="featured" />
                                <Link
                                    v-if="account.status === 'unverified'"
                                    :href="verify(account.ulid).url"
                                    :aria-label="`Verify ${account.name}`"
                                    class="text-sm font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                >
                                    Verify
                                </Link>
                            </li>
                        </ul>
                        <div>
                            <UiButton :href="attach().url" variant="secondary" size="sm">Attach another account</UiButton>
                        </div>
                    </div>
                    <UiEmptyState v-else title="No accounts yet" body="Attach your Clash of Clans account and verify it with an in-game token.">
                        <template #action>
                            <UiButton :href="attach().url">Attach an account</UiButton>
                        </template>
                    </UiEmptyState>
                </template>
                <p v-else class="py-6 text-body text-fg-muted">No public accounts.</p>
            </template>
            <template #bases>
                <UiEmptyState v-if="profile.isOwn" title="No bases yet" body="Bases you publish will show here once publishing opens." />
                <p v-else class="py-6 text-body text-fg-muted">No published bases yet.</p>
            </template>
        </UiTabs>
    </article>
</template>
