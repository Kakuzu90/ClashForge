<script setup lang="ts">
import { formatDateTime } from '@/Composables/useDateTime';
import { read } from '@/routes/notifications';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Item = App.Domain.Notifications.Data.NotificationItemData;

// One row of the notification centre (specs/18 §6). Unread rows carry the gold left border and a
// "New" label, so the state is not colour alone. Opening one marks it read and follows its link;
// a read row with nowhere to go is plain text.
const props = defineProps<{ notification: Item }>();

const interactive = computed(() => !props.notification.read || props.notification.hasTarget);
</script>

<template>
    <component
        :is="interactive ? Link : 'div'"
        v-bind="interactive ? { href: read(notification.id).url, method: 'post', as: 'button', preserveScroll: !notification.hasTarget } : {}"
        class="flex w-full flex-col gap-1 border-l-4 px-4 py-3 text-left"
        :class="[
            notification.read ? 'border-l-transparent' : 'border-l-brand bg-surface-raised',
            interactive ? 'hover:bg-surface-hover focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus' : '',
        ]"
    >
        <span class="flex flex-wrap items-baseline gap-x-2">
            <span v-if="!notification.read" class="text-xs text-brand uppercase">New</span>
            <span class="font-semibold text-fg">{{ notification.title }}</span>
        </span>
        <span v-if="notification.body" class="text-sm break-words text-fg-secondary">{{ notification.body }}</span>
        <time class="text-xs text-fg-muted normal-case" :datetime="notification.createdAt">{{ formatDateTime(notification.createdAt) }}</time>
    </component>
</template>
