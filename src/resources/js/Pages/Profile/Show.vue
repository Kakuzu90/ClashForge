<script setup lang="ts">
import ProfileSkeleton from '@/Components/profile/ProfileSkeleton.vue';
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiStatBlock from '@/Components/ui/UiStatBlock.vue';
import UiTabs, { type TabItem } from '@/Components/ui/UiTabs.vue';
import { formatMonthYear } from '@/Composables/useDateTime';
import { useNavigating } from '@/Composables/useNavigating';
import AppLayout from '@/Layouts/AppLayout.vue';
import { show } from '@/routes/profile';
import { edit as privacySettings } from '@/routes/settings/privacy';
import { edit as profileSettings } from '@/routes/settings/profile';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ profile: App.Http.Data.Profile.ProfileShowPageData['profile'] }>();

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
                <UiEmptyState
                    v-if="profile.isOwn"
                    title="No accounts yet"
                    body="Your verified Clash of Clans accounts will show here once account linking opens."
                />
                <p v-else class="py-6 text-body text-fg-muted">No public accounts.</p>
            </template>
            <template #bases>
                <UiEmptyState v-if="profile.isOwn" title="No bases yet" body="Bases you publish will show here once publishing opens." />
                <p v-else class="py-6 text-body text-fg-muted">No published bases yet.</p>
            </template>
        </UiTabs>
    </article>
</template>
