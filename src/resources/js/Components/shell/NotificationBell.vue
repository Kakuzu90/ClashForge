<script setup lang="ts">
import { usePageProps } from '@/Composables/usePageProps';
import { useUnreadPoll } from '@/Composables/useUnreadPoll';
import { index } from '@/routes/notifications';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// The header bell (specs/16 §6): the unread count, capped at 99+, and a link to the notification
// centre. The count is announced politely when it changes (specs/18 §8).
const { unreadCount, url } = usePageProps();

useUnreadPoll();

const count = computed(() => unreadCount.value ?? 0);
const shown = computed(() => (count.value > 99 ? '99+' : String(count.value)));
const label = computed(() => (count.value === 0 ? 'Notifications' : `Notifications, ${shown.value} unread`));
const current = computed(() => url.value.split(/[?#]/)[0] === index().url);
</script>

<template>
    <Link
        :href="index().url"
        :aria-label="label"
        :aria-current="current ? 'page' : undefined"
        class="hit-target relative flex size-10 items-center justify-center rounded-full text-fg-secondary hover:bg-surface-hover hover:text-fg"
    >
        <svg
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z" />
            <path d="M10 20.5a2 2 0 0 0 4 0" />
        </svg>
        <span
            v-if="count > 0"
            class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1 text-xs text-fg-on-gold tabular-nums"
            aria-hidden="true"
            >{{ shown }}</span
        >
    </Link>
    <span class="sr-only" aria-live="polite">{{ count === 0 ? '' : `${shown} unread notifications` }}</span>
</template>
