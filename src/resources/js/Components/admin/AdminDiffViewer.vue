<script lang="ts">
export type DiffKind = 'added' | 'removed' | 'changed' | 'unchanged';

export interface DiffRow {
    key: string;
    kind: DiffKind;
    before: string | null;
    after: string | null;
}

type Fields = { [key: string]: unknown } | null;

function show(value: unknown): string {
    return typeof value === 'string' ? value : JSON.stringify(value);
}

/**
 * Top-level keys of both records, in first-seen order, each with its change. Nested values are
 * compared as JSON, so a changed object shows as one changed row.
 */
export function diffRecords(before: Fields, after: Fields): DiffRow[] {
    const keys = [...new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})])];

    return keys.map((key) => {
        const had = before !== null && Object.hasOwn(before, key);
        const has = after !== null && Object.hasOwn(after, key);
        const b = had ? show(before[key]) : null;
        const a = has ? show(after[key]) : null;
        const kind: DiffKind = !had ? 'added' : !has ? 'removed' : a === b ? 'unchanged' : 'changed';

        return { key, kind, before: b, after: a };
    });
}
</script>

<script setup lang="ts">
import { computed } from 'vue';

// specs/18 §4 DiffViewer: before and after side by side, values shown as text, never as HTML.
// The change is spelled out in words; colour only backs it up.
const props = defineProps<{ before: Fields; after: Fields }>();

const rows = computed(() => diffRecords(props.before, props.after));

const labels: { [K in DiffKind]: string } = { added: 'Added', removed: 'Removed', changed: 'Changed', unchanged: 'Same' };
const tone: { [K in DiffKind]: string } = {
    added: 'border-l-success',
    removed: 'border-l-danger',
    changed: 'border-l-warning',
    unchanged: 'border-l-line',
};
</script>

<template>
    <p v-if="rows.length === 0" class="text-sm text-fg-secondary">No before or after values recorded.</p>
    <table v-else class="w-full border-collapse text-left text-sm">
        <thead>
            <tr class="text-fg-secondary">
                <th scope="col" class="px-2 py-1 font-semibold">Field</th>
                <th scope="col" class="px-2 py-1 font-semibold">Before</th>
                <th scope="col" class="px-2 py-1 font-semibold">After</th>
                <th scope="col" class="px-2 py-1 font-semibold">Change</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="row.key" class="border-l-4" :class="tone[row.kind]">
                <th scope="row" class="px-2 py-1 font-medium text-fg">{{ row.key }}</th>
                <td class="px-2 py-1 break-all text-fg-secondary">
                    <span v-if="row.before === null" class="text-fg-muted">None</span>
                    <template v-else>{{ row.before }}</template>
                </td>
                <td class="px-2 py-1 break-all text-fg">
                    <span v-if="row.after === null" class="text-fg-muted">None</span>
                    <template v-else>{{ row.after }}</template>
                </td>
                <td class="px-2 py-1 text-fg-secondary">{{ labels[row.kind] }}</td>
            </tr>
        </tbody>
    </table>
</template>
