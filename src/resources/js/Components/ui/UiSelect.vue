<script setup lang="ts" generic="T extends string">
import { computed, nextTick, ref, useId, watch } from 'vue';

export interface SelectOption<V extends string = string> {
    value: V;
    label: string;
    /** Secondary line shown under the label in the searchable list. */
    hint?: string;
}

const props = withDefaults(
    defineProps<{
        label: string;
        options: SelectOption<T>[];
        /** Typeahead combobox (WAI-ARIA 1.2 pattern) instead of the native select. */
        searchable?: boolean;
        placeholder?: string;
        hint?: string;
        error?: string;
        disabled?: boolean;
        required?: boolean;
        noResultsText?: string;
        id?: string;
    }>(),
    { noResultsText: 'No matches' },
);

const model = defineModel<T | null>({ default: null });

const generatedId = useId();
const inputId = computed(() => props.id ?? generatedId);
const ids = computed(() => ({
    hint: `${inputId.value}-hint`,
    error: `${inputId.value}-error`,
    listbox: `${inputId.value}-listbox`,
    option: (index: number) => `${inputId.value}-option-${index}`,
}));
const describedBy = computed(() => [props.error ? ids.value.error : null, props.hint ? ids.value.hint : null].filter(Boolean).join(' ') || undefined);

const selected = computed(() => props.options.find((o) => o.value === model.value) ?? null);

// Searchable state
const open = ref(false);
const query = ref('');
const active = ref(-1);
const input = ref<HTMLInputElement | null>(null);
const list = ref<HTMLUListElement | null>(null);

const filtered = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return needle === ''
        ? props.options
        : props.options.filter((o) => o.label.toLowerCase().includes(needle) || o.hint?.toLowerCase().includes(needle));
});

// Closed, the field shows the chosen label; open, it shows what the user typed.
const inputValue = computed(() => (open.value ? query.value : (selected.value?.label ?? '')));

watch(filtered, (options) => {
    if (active.value >= options.length) {
        active.value = options.length - 1;
    }
});

function show() {
    if (props.disabled || open.value) return;
    open.value = true;
    query.value = '';
    const index = props.options.findIndex((o) => o.value === model.value);
    active.value = index >= 0 ? index : 0;
    scrollActive();
}

function close() {
    open.value = false;
    query.value = '';
    active.value = -1;
}

function choose(option: SelectOption<T>) {
    model.value = option.value;
    close();
}

function move(step: number) {
    if (!open.value) return show();
    const count = filtered.value.length;
    if (count === 0) return;
    active.value = (active.value + step + count) % count;
    scrollActive();
}

function scrollActive() {
    nextTick(() => list.value?.querySelector<HTMLElement>(`#${CSS.escape(ids.value.option(active.value))}`)?.scrollIntoView({ block: 'nearest' }));
}

function onInput(event: Event) {
    query.value = (event.target as HTMLInputElement).value;
    open.value = true;
    active.value = filtered.value.length > 0 ? 0 : -1;
}

function onKeydown(event: KeyboardEvent) {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Home':
        case 'End':
            if (open.value && filtered.value.length > 0) {
                event.preventDefault();
                active.value = event.key === 'Home' ? 0 : filtered.value.length - 1;
                scrollActive();
            }
            break;
        case 'Enter': {
            const option = filtered.value[active.value];
            if (open.value && option) {
                event.preventDefault();
                choose(option);
            }
            break;
        }
        case 'Escape':
            if (open.value) {
                event.preventDefault();
                close();
            }
            break;
        case 'Tab':
            close();
            break;
    }
}

function toggle() {
    if (open.value) {
        close();
    } else {
        input.value?.focus();
        show();
    }
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="inputId" class="text-sm font-medium text-fg-secondary">
            {{ label }}<span v-if="required" class="text-danger-fg" aria-hidden="true"> *</span>
        </label>

        <!-- Native: the platform picker is the best control on phones (specs/18 §4 "native-styled"). -->
        <div v-if="!searchable" class="relative">
            <select
                :id="inputId"
                v-model="model"
                :disabled="disabled"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="h-11 w-full appearance-none rounded-sm border bg-surface pr-10 pl-3 text-body text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:opacity-60 sm:h-10"
                :class="error ? 'border-danger' : 'border-control'"
            >
                <option v-if="placeholder" :value="null" disabled>{{ placeholder }}</option>
                <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <svg
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-fg-muted"
                width="16"
                height="16"
                viewBox="0 0 16 16"
                aria-hidden="true"
            >
                <path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>

        <div v-else class="relative">
            <div
                class="flex h-11 items-center rounded-sm border focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus sm:h-10"
                :class="[error ? 'border-danger' : 'border-control', disabled ? 'opacity-60' : 'bg-surface']"
            >
                <input
                    :id="inputId"
                    ref="input"
                    type="text"
                    role="combobox"
                    autocomplete="off"
                    aria-autocomplete="list"
                    :aria-expanded="open ? 'true' : 'false'"
                    :aria-controls="ids.listbox"
                    :aria-activedescendant="open && active >= 0 && filtered[active] ? ids.option(active) : undefined"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="describedBy"
                    :aria-required="required || undefined"
                    :disabled="disabled"
                    :placeholder="open && selected ? selected.label : placeholder"
                    :value="inputValue"
                    class="h-full w-full min-w-0 bg-transparent px-3 text-body text-fg outline-none placeholder:text-fg-muted disabled:cursor-not-allowed"
                    @input="onInput"
                    @keydown="onKeydown"
                    @click="show"
                    @blur="close"
                />
                <button
                    type="button"
                    tabindex="-1"
                    :aria-label="open ? `Hide ${label} options` : `Show ${label} options`"
                    :disabled="disabled"
                    class="hit-target flex h-full w-10 shrink-0 items-center justify-center text-fg-muted hover:text-fg disabled:cursor-not-allowed"
                    @mousedown.prevent="toggle"
                >
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" :class="open ? 'rotate-180' : ''">
                        <path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <ul
                v-show="open"
                :id="ids.listbox"
                ref="list"
                role="listbox"
                :aria-label="label"
                class="absolute top-full right-0 left-0 z-100 mt-1 max-h-64 overflow-y-auto rounded-md border border-b-[3px] border-line border-b-line-strong bg-surface-raised py-1"
            >
                <li
                    v-for="(option, index) in filtered"
                    :id="ids.option(index)"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === model ? 'true' : 'false'"
                    class="flex min-h-11 cursor-pointer flex-col justify-center px-3 py-2 text-body"
                    :class="[
                        index === active ? 'bg-surface-hover text-fg' : 'text-fg-secondary',
                        option.value === model ? 'font-semibold text-fg' : '',
                    ]"
                    @mousedown.prevent="choose(option)"
                    @mousemove="active = index"
                >
                    <span class="flex items-center justify-between gap-2">
                        {{ option.label }}
                        <svg v-if="option.value === model" class="shrink-0 text-brand" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                            <path
                                d="M3.5 8.5l3 3 6-7"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span v-if="option.hint" class="text-sm text-fg-muted">{{ option.hint }}</span>
                </li>
                <li v-if="filtered.length === 0" class="px-3 py-2 text-body text-fg-muted" role="presentation">{{ noResultsText }}</li>
            </ul>
            <span class="sr-only" aria-live="polite">{{ open ? `${filtered.length} ${filtered.length === 1 ? 'option' : 'options'}` : '' }}</span>
        </div>

        <p v-if="error" :id="ids.error" class="text-sm text-danger-fg">{{ error }}</p>
        <p v-if="hint" :id="ids.hint" class="text-sm text-fg-muted">{{ hint }}</p>
    </div>
</template>
