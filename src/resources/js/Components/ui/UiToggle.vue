<script setup lang="ts">
import { computed, useId } from 'vue';

// An on/off switch (specs/18 §4): a native checkbox with role="switch", so it submits, focuses and
// toggles with Space like any checkbox. The label row is the 44px touch target.
const props = defineProps<{
    label: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    id?: string;
}>();

const model = defineModel<boolean>({ default: false });

const generatedId = useId();
const inputId = computed(() => props.id ?? generatedId);
const describedBy = computed(
    () => [props.error ? `${inputId.value}-error` : null, props.hint ? `${inputId.value}-hint` : null].filter(Boolean).join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label
            :for="inputId"
            class="flex min-h-11 items-center justify-between gap-4 text-body text-fg"
            :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
        >
            <span>{{ label }}</span>
            <span class="relative inline-flex h-6 w-11 shrink-0">
                <input
                    :id="inputId"
                    v-model="model"
                    type="checkbox"
                    role="switch"
                    :disabled="disabled"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="describedBy"
                    class="peer h-6 w-11 cursor-[inherit] appearance-none rounded-full border-2 border-control bg-surface transition-colors duration-200 checked:border-brand checked:bg-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                />
                <span
                    class="pointer-events-none absolute top-1 left-1 size-4 rounded-full bg-fg-secondary transition-transform duration-200 peer-checked:translate-x-5 peer-checked:bg-fg-on-gold"
                    aria-hidden="true"
                />
            </span>
        </label>
        <p v-if="error" :id="`${inputId}-error`" class="text-sm text-danger-fg">{{ error }}</p>
        <p v-if="hint" :id="`${inputId}-hint`" class="text-sm text-fg-muted">{{ hint }}</p>
    </div>
</template>
