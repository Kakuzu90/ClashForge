<script setup lang="ts">
import { computed, nextTick, ref, useId } from 'vue';

export type TabsVariant = 'underline' | 'pill';

export interface TabItem {
    key: string;
    label: string;
}

// WAI-ARIA tabs (specs/18 §4): one tab stop, arrow keys move between tabs and activate them,
// Home/End jump to the ends. Each panel is a named slot, `#<key>`.
const props = withDefaults(
    defineProps<{
        tabs: TabItem[];
        /** Accessible name of the tab list. */
        label: string;
        variant?: TabsVariant;
    }>(),
    { variant: 'underline' },
);

const active = defineModel<string>();
const current = computed(() => (props.tabs.some((tab) => tab.key === active.value) ? active.value : props.tabs[0]?.key));

const id = useId();
const tabId = (key: string) => `${id}-tab-${key}`;
const panelId = (key: string) => `${id}-panel-${key}`;
const buttons = ref<HTMLButtonElement[]>([]);

function select(index: number) {
    const tab = props.tabs[index];
    if (!tab) {
        return;
    }
    active.value = tab.key;
    void nextTick(() => buttons.value[index]?.focus());
}

function onKeydown(event: KeyboardEvent, index: number) {
    const last = props.tabs.length - 1;
    const target = { ArrowRight: index === last ? 0 : index + 1, ArrowLeft: index === 0 ? last : index - 1, Home: 0, End: last }[event.key];

    if (target !== undefined) {
        event.preventDefault();
        select(target);
    }
}

const tabClass = (selected: boolean) =>
    props.variant === 'pill'
        ? ['min-h-11 rounded-full px-4 text-sm font-semibold', selected ? 'bg-surface-raised text-fg' : 'text-fg-secondary hover:text-fg']
        : [
              '-mb-px min-h-11 border-b-2 px-4 text-body font-semibold',
              selected ? 'border-brand text-fg' : 'border-transparent text-fg-secondary hover:border-line-strong hover:text-fg',
          ];
</script>

<template>
    <div>
        <div
            role="tablist"
            :aria-label="label"
            class="flex gap-1 overflow-x-auto overflow-y-hidden"
            :class="variant === 'underline' ? 'border-b border-line' : 'rounded-full bg-surface p-1'"
        >
            <button
                v-for="(tab, index) in tabs"
                :id="tabId(tab.key)"
                :key="tab.key"
                ref="buttons"
                type="button"
                role="tab"
                :aria-selected="tab.key === current"
                :aria-controls="panelId(tab.key)"
                :tabindex="tab.key === current ? 0 : -1"
                class="shrink-0 cursor-pointer whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                :class="tabClass(tab.key === current)"
                @click="select(index)"
                @keydown="onKeydown($event, index)"
            >
                {{ tab.label }}
            </button>
        </div>
        <div
            v-for="tab in tabs"
            v-show="tab.key === current"
            :id="panelId(tab.key)"
            :key="tab.key"
            role="tabpanel"
            :aria-labelledby="tabId(tab.key)"
            tabindex="0"
            class="pt-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
        >
            <slot :name="tab.key" />
        </div>
    </div>
</template>
