<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

export interface MenuItem {
    key: string;
    label: string;
    /** Renders an Inertia link; without it the item is a button that emits `select`. */
    href?: string;
}

// Dropdown menu (specs/18 §4), WAI-ARIA menu button: Enter, Space or ArrowDown opens on the first
// item, ArrowUp on the last; arrows, Home and End move; Escape closes and returns focus; Tab and a
// click outside close.
const props = withDefaults(
    defineProps<{
        items: MenuItem[];
        /** Accessible name of the trigger, e.g. "chief, account menu". */
        label: string;
        align?: 'start' | 'end';
    }>(),
    { align: 'end' },
);

const emit = defineEmits<{ select: [key: string] }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const itemEls = ref<HTMLElement[]>([]);
const menuId = useId();

// Links are components, so their ref is an instance; keep the element, in item order.
function setItem(el: unknown, index: number) {
    const element = el instanceof HTMLElement ? el : (el as { $el?: unknown } | null)?.$el;
    if (element instanceof HTMLElement) {
        itemEls.value[index] = element;
    }
}

function focusItem(index: number) {
    const count = props.items.length;
    const target = itemEls.value[((index % count) + count) % count];
    target?.focus();
}

async function show(focus: 'first' | 'last') {
    open.value = true;
    await nextTick();
    focusItem(focus === 'first' ? 0 : props.items.length - 1);
}

function hide(returnFocus = true) {
    if (!open.value) {
        return;
    }
    open.value = false;
    if (returnFocus) {
        trigger.value?.focus();
    }
}

function onTriggerKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        void show('first');
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        void show('last');
    }
}

function onMenuKeydown(event: KeyboardEvent) {
    const current = itemEls.value.indexOf(document.activeElement as HTMLElement);
    const moves: Record<string, number> = { ArrowDown: current + 1, ArrowUp: current - 1, Home: 0, End: props.items.length - 1 };

    if (event.key in moves) {
        event.preventDefault();
        focusItem(moves[event.key]!);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        hide();
    } else if (event.key === 'Tab') {
        hide(false);
    }
}

function choose(item: MenuItem) {
    hide(false);
    if (!item.href) {
        emit('select', item.key);
    }
}

function onDocumentClick(event: MouseEvent) {
    if (open.value && root.value && !root.value.contains(event.target as Node)) {
        hide(false);
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            ref="trigger"
            type="button"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-controls="open ? menuId : undefined"
            :aria-label="label"
            class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-1 text-sm font-medium text-fg-secondary hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            @click="open ? hide(false) : show('first')"
            @keydown="onTriggerKeydown"
        >
            <slot name="trigger" />
        </button>
        <ul
            v-if="open"
            :id="menuId"
            role="menu"
            :aria-label="label"
            class="absolute top-full z-100 mt-1 min-w-48 rounded-lg border border-line bg-surface-raised py-1 shadow-modal"
            :class="align === 'end' ? 'right-0' : 'left-0'"
            @keydown="onMenuKeydown"
        >
            <li v-for="(item, index) in items" :key="item.key" role="none">
                <Link
                    v-if="item.href"
                    :ref="(el) => setItem(el, index)"
                    :href="item.href"
                    role="menuitem"
                    tabindex="-1"
                    class="flex min-h-11 items-center px-4 text-body text-fg hover:bg-surface-hover focus:bg-surface-hover focus:outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus"
                    @click="choose(item)"
                >
                    {{ item.label }}
                </Link>
                <button
                    v-else
                    :ref="(el) => setItem(el, index)"
                    type="button"
                    role="menuitem"
                    tabindex="-1"
                    class="flex min-h-11 w-full cursor-pointer items-center px-4 text-left text-body text-fg hover:bg-surface-hover focus:bg-surface-hover focus:outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus"
                    @click="choose(item)"
                >
                    {{ item.label }}
                </button>
            </li>
        </ul>
    </div>
</template>
