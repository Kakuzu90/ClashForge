<script setup lang="ts" generic="T">
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { ref, useId } from 'vue';

export interface AdminColumn {
    key: string;
    label: string;
    /** Width and alignment classes, shared by the header, the cells and the skeleton rows. */
    class?: string;
}

// specs/18 §4 DataTable, plain: dense rows, sticky header inside its own scroll area (keyboard
// scrollable), skeleton rows that keep the column widths, and optional expandable detail rows.
const props = withDefaults(
    defineProps<{
        columns: AdminColumn[];
        rows: T[];
        rowKey: (row: T) => string | number;
        /** Names the table and its scroll area for screen readers. */
        caption: string;
        loading?: boolean;
        skeletonRows?: number;
        /** Adds a toggle per row that opens the `detail` slot under it. */
        expandable?: boolean;
        /** Accessible name of a row's toggle, e.g. "Details for role changed on 1 Oct". */
        expandLabel?: (row: T) => string;
    }>(),
    { loading: false, skeletonRows: 5, expandable: false, expandLabel: undefined },
);

defineSlots<{
    [key: `cell-${string}`]: (props: { row: T }) => unknown;
    detail?: (props: { row: T }) => unknown;
    empty?: () => unknown;
}>();

const id = useId();
const open = ref(new Set<string | number>());

function toggle(row: T) {
    const key = props.rowKey(row);
    const next = new Set(open.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    open.value = next;
}

const isOpen = (row: T) => open.value.has(props.rowKey(row));
const detailId = (row: T) => `${id}-detail-${props.rowKey(row)}`;
const cell = (row: T, key: string) => (row as { [key: string]: unknown })[key];
const span = () => props.columns.length + (props.expandable ? 1 : 0);
</script>

<template>
    <div class="max-h-[70dvh] overflow-auto rounded-sm border border-line bg-surface" tabindex="0" role="region" :aria-label="caption">
        <table class="w-full border-collapse text-left text-sm">
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <thead>
                <tr>
                    <th v-if="expandable" scope="col" class="sticky top-0 z-10 w-12 border-b border-line bg-surface px-2 py-2">
                        <span class="sr-only">Details</span>
                    </th>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="sticky top-0 z-10 border-b border-line bg-surface px-3 py-2 font-semibold whitespace-nowrap text-fg-secondary"
                        :class="column.class"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody v-if="loading">
                <tr v-for="n in skeletonRows" :key="n" class="border-b border-line-subtle last:border-b-0">
                    <td v-if="expandable" class="px-2 py-2">
                        <span v-if="n === 1" class="sr-only" role="status">Loading</span>
                    </td>
                    <td v-for="column in columns" :key="column.key" class="px-3 py-2" :class="column.class">
                        <UiSkeleton :lines="1" />
                    </td>
                </tr>
            </tbody>
            <tbody v-else-if="rows.length === 0">
                <tr>
                    <td :colspan="span()" class="px-3 py-8 text-center text-fg-secondary">
                        <slot name="empty">Nothing to show.</slot>
                    </td>
                </tr>
            </tbody>
            <tbody v-else>
                <template v-for="row in rows" :key="rowKey(row)">
                    <tr class="border-b border-line-subtle align-top last:border-b-0" :class="isOpen(row) ? 'bg-surface-raised' : ''">
                        <td v-if="expandable" class="px-1 py-1">
                            <button
                                type="button"
                                class="hit-target flex size-10 items-center justify-center rounded-sm text-fg-secondary hover:bg-surface-hover hover:text-fg"
                                :aria-expanded="isOpen(row) ? 'true' : 'false'"
                                :aria-controls="detailId(row)"
                                :aria-label="expandLabel ? expandLabel(row) : 'Details'"
                                @click="toggle(row)"
                            >
                                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" :class="isOpen(row) ? 'rotate-90' : ''">
                                    <path
                                        d="M6 4l4 4-4 4"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>
                        </td>
                        <td v-for="column in columns" :key="column.key" class="px-3 py-2 text-fg" :class="column.class">
                            <slot :name="`cell-${column.key}`" :row="row">{{ cell(row, column.key) }}</slot>
                        </td>
                    </tr>
                    <tr
                        v-if="expandable"
                        v-show="isOpen(row)"
                        :id="detailId(row)"
                        class="border-b border-line-subtle bg-surface-raised last:border-b-0"
                    >
                        <td :colspan="span()" class="px-3 pt-1 pb-4">
                            <slot v-if="isOpen(row)" name="detail" :row="row" />
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
