<script setup lang="ts">
import AdminDiffViewer from '@/Components/admin/AdminDiffViewer.vue';
import { formatDateTime } from '@/Composables/useDateTime';

type Entry = App.Http.Data.Admin.AuditTrailEntryData;

// specs/18 §4 AuditTrailList: the latest audit entries about one record, newest first, each with
// who acted and what changed. Values render as text.
defineProps<{ entries: Entry[] }>();

function actor(entry: Entry): string {
    if (entry.actorUsername) return entry.actorRoleLabel ? `${entry.actorUsername} (${entry.actorRoleLabel})` : entry.actorUsername;
    return entry.actorVia === 'console' ? 'Console' : 'System';
}
</script>

<template>
    <p v-if="entries.length === 0" class="text-sm text-fg-secondary">Nothing has been logged about this account.</p>
    <ol v-else class="flex flex-col divide-y divide-line-subtle rounded-sm border border-line">
        <li v-for="entry in entries" :key="entry.id" class="flex flex-col gap-2 p-3">
            <p class="text-sm">
                <span class="font-semibold text-fg">{{ entry.actionLabel }}</span
                >{{ ' ' }}<span class="text-fg-secondary">by {{ actor(entry) }}</span
                >{{ ' · ' }}<time class="text-fg-muted" :datetime="entry.createdAt">{{ formatDateTime(entry.createdAt) }}</time>
            </p>
            <AdminDiffViewer :before="entry.before" :after="entry.after" />
        </li>
    </ol>
</template>
