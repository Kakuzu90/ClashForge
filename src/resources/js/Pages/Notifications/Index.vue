<script setup lang="ts">
import NotificationItem from '@/Components/notifications/NotificationItem.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { useNavigating } from '@/Composables/useNavigating';
import { usePageProps } from '@/Composables/usePageProps';
import { useVisitError } from '@/Composables/useVisitError';
import AppLayout from '@/Layouts/AppLayout.vue';
import { index, readAll } from '@/routes/notifications';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

type Page = App.Http.Data.Notifications.NotificationIndexPageData;

// FR-NOTIF-1, specs/18 §6: category tabs, unread rows with the gold border, newest first, paged.
const props = defineProps<{
    category: Page['category'];
    tabs: Page['tabs'];
    notifications: Page['notifications'];
}>();

const { unreadCount } = usePageProps();
const navigating = useNavigating((url) => url.pathname === index().url);
const visitError = useVisitError();
const markingAll = ref(false);

const tabUrl = (value: string | null) => index(value === null ? undefined : { query: { category: value } }).url;
const activeLabel = computed(() => props.tabs.find((tab) => tab.value === props.category)?.label ?? 'All');

function markAllRead() {
    router.post(readAll().url, {}, { preserveScroll: true, onStart: () => (markingAll.value = true), onFinish: () => (markingAll.value = false) });
}

function goTo(cursor: string) {
    router.get(index().url, { ...(props.category ? { category: props.category } : {}), cursor });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-display text-h1 text-fg">Notifications</h1>
            <UiButton v-if="(unreadCount ?? 0) > 0" variant="secondary" size="sm" :disabled="markingAll" @click="markAllRead">
                {{ markingAll ? 'Marking…' : 'Mark all as read' }}
            </UiButton>
        </div>

        <nav aria-label="Filter notifications" class="flex flex-wrap gap-2">
            <Link
                v-for="tab in tabs"
                :key="tab.value ?? 'all'"
                :href="tabUrl(tab.value)"
                :aria-current="tab.value === category ? 'page' : undefined"
                preserve-scroll
                class="hit-target shrink-0 rounded-full border px-3 py-1.5 text-sm font-semibold whitespace-nowrap"
                :class="
                    tab.value === category
                        ? 'border-brand bg-brand/20 text-fg'
                        : 'border-line text-fg-secondary hover:border-line-strong hover:text-fg'
                "
                >{{ tab.label }}</Link
            >
        </nav>

        <UiAlert v-if="visitError" kind="danger" title="Notifications didn't load">
            <p>
                Try again in a moment.<template v-if="visitError.requestId">
                    If it keeps failing, quote request id <span class="font-semibold">{{ visitError.requestId }}</span
                    >.</template
                >
            </p>
            <UiButton class="mt-3" variant="secondary" size="sm" @click="router.reload()">Try again</UiButton>
        </UiAlert>

        <div v-if="navigating" class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-4">
            <UiSkeleton label="Loading notifications" :lines="2" />
            <UiSkeleton :lines="2" />
            <UiSkeleton :lines="2" />
        </div>

        <UiEmptyState
            v-else-if="notifications.entries.length === 0"
            :title="category === null ? 'You\'re all caught up' : `Nothing in ${activeLabel}`"
            :body="
                category === null
                    ? 'Security alerts and updates about your uploads will show up here.'
                    : 'Notifications in this category will show up here.'
            "
        >
            <template #illustration>
                <svg width="72" height="72" viewBox="0 0 72 72" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="36" cy="36" r="32" class="fill-surface-raised stroke-line" stroke-width="2" />
                    <path d="M24 44V34a12 12 0 0 1 24 0v10l3 4H21z" class="stroke-fg-secondary" stroke-width="3" />
                    <path d="M31 52a5 5 0 0 0 10 0" class="stroke-fg-secondary" stroke-width="3" />
                    <path d="M29 36l5 5 9-10" class="stroke-success" stroke-width="3.5" />
                </svg>
            </template>
        </UiEmptyState>

        <template v-else>
            <ol class="flex flex-col divide-y divide-line-subtle overflow-hidden rounded-lg border border-line bg-surface">
                <li v-for="notification in notifications.entries" :key="notification.id">
                    <NotificationItem :notification="notification" />
                </li>
            </ol>

            <nav v-if="notifications.newerCursor || notifications.olderCursor" aria-label="Notification pages" class="flex flex-wrap gap-2">
                <UiButton v-if="notifications.newerCursor" variant="secondary" size="sm" @click="goTo(notifications.newerCursor)">Newer</UiButton>
                <UiButton v-if="notifications.olderCursor" variant="secondary" size="sm" @click="goTo(notifications.olderCursor)">Older</UiButton>
            </nav>
        </template>
    </div>
</template>
