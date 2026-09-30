<script setup lang="ts">
import { useFocusTrap } from '@/Composables/useFocusTrap';
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

const props = defineProps<{
    title: string;
    description?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const panel = ref<HTMLElement | null>(null);
const titleId = useId();
const descriptionId = useId();

function close() {
    open.value = false;
}

useFocusTrap(panel, open, close);

// Lock page scroll while open. Client only: watchers and hooks below never run during SSR.
function lockScroll(locked: boolean) {
    document.body.style.overflow = locked ? 'hidden' : '';
}

watch(open, lockScroll);
onMounted(() => open.value && lockScroll(true));
onBeforeUnmount(() => open.value && lockScroll(false));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            leave-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-300 flex items-end justify-center bg-scrim sm:items-center sm:p-4" @click.self="close">
                <Transition
                    appear
                    enter-active-class="transition-transform duration-250 ease-out"
                    enter-from-class="translate-y-full sm:translate-y-4"
                >
                    <div
                        ref="panel"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="titleId"
                        :aria-describedby="props.description ? descriptionId : undefined"
                        tabindex="-1"
                        class="max-h-[90dvh] w-full overflow-y-auto rounded-t-xl border border-line bg-surface p-6 shadow-modal sm:max-w-lg sm:rounded-xl"
                    >
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <div>
                                <h2 :id="titleId" class="font-display text-h2">{{ title }}</h2>
                                <p v-if="description" :id="descriptionId" class="mt-1 text-sm text-fg-secondary">{{ description }}</p>
                            </div>
                            <button
                                type="button"
                                class="hit-target inline-flex size-10 shrink-0 items-center justify-center rounded-md text-fg-secondary hover:bg-surface-raised hover:text-fg"
                                aria-label="Close"
                                @click="close"
                            >
                                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                                    <path d="M3.5 3.5l9 9M12.5 3.5l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                        <slot />
                        <div v-if="$slots.footer" class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <slot name="footer" :close="close" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
