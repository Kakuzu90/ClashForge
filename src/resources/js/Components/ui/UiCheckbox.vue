<script setup lang="ts">
import { computed, useId } from 'vue';

// Native checkbox, custom-drawn (specs/18 §4). The whole label row is the 44px touch target.
const props = defineProps<{
    label: string;
    hint?: string;
    error?: string;
    disabled?: boolean;
    indeterminate?: boolean;
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
            class="flex min-h-11 items-center gap-3 text-body text-fg"
            :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
        >
            <span class="relative inline-flex size-5 shrink-0">
                <input
                    :id="inputId"
                    v-model="model"
                    type="checkbox"
                    :disabled="disabled"
                    :indeterminate="indeterminate"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="describedBy"
                    class="peer size-5 appearance-none rounded-sm border-2 bg-surface checked:border-brand checked:bg-brand indeterminate:border-brand indeterminate:bg-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed"
                    :class="error ? 'border-danger' : 'border-control'"
                />
                <svg
                    class="pointer-events-none absolute inset-0 hidden text-fg-on-gold peer-checked:block peer-indeterminate:hidden"
                    viewBox="0 0 20 20"
                    aria-hidden="true"
                >
                    <path
                        d="M5.5 10.2l3 3 6-6.4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.4"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <svg
                    class="pointer-events-none absolute inset-0 hidden text-fg-on-gold peer-indeterminate:block"
                    viewBox="0 0 20 20"
                    aria-hidden="true"
                >
                    <path d="M5.5 10h9" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
                </svg>
            </span>
            {{ label }}
        </label>
        <p v-if="error" :id="`${inputId}-error`" class="pl-8 text-sm text-danger-fg">{{ error }}</p>
        <p v-if="hint" :id="`${inputId}-hint`" class="pl-8 text-sm text-fg-muted">{{ hint }}</p>
    </div>
</template>
