<script setup lang="ts">
import { computed, useId } from 'vue';

export interface RadioOption {
    value: string;
    label: string;
    description?: string;
}

// Native radios in a fieldset (specs/18 §4): arrow keys move the choice, the legend names the
// group, and each option row is a 44px touch target.
const props = defineProps<{
    legend: string;
    options: RadioOption[];
    error?: string;
    disabled?: boolean;
    name?: string;
}>();

const model = defineModel<string>();

const id = useId();
const groupName = computed(() => props.name ?? id);
const optionId = (value: string) => `${id}-${value}`;
</script>

<template>
    <fieldset :aria-describedby="error ? `${id}-error` : undefined" :disabled="disabled" class="flex flex-col gap-2">
        <legend class="mb-1 text-body font-semibold text-fg">{{ legend }}</legend>
        <label
            v-for="option in options"
            :key="option.value"
            :for="optionId(option.value)"
            class="flex min-h-11 items-start gap-3 rounded-md border px-3 py-3"
            :class="[
                model === option.value ? 'border-brand bg-surface-raised' : 'border-line hover:border-line-strong',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            ]"
        >
            <input
                :id="optionId(option.value)"
                v-model="model"
                type="radio"
                :name="groupName"
                :value="option.value"
                :aria-describedby="option.description ? `${optionId(option.value)}-description` : undefined"
                class="mt-0.5 size-5 shrink-0 cursor-[inherit] appearance-none rounded-full border-2 border-control bg-surface checked:border-[6px] checked:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            />
            <span class="flex flex-col gap-0.5">
                <span class="text-body font-semibold text-fg">{{ option.label }}</span>
                <span v-if="option.description" :id="`${optionId(option.value)}-description`" class="text-sm text-fg-secondary">
                    {{ option.description }}
                </span>
            </span>
        </label>
        <p v-if="error" :id="`${id}-error`" role="alert" class="text-sm text-danger-fg">{{ error }}</p>
    </fieldset>
</template>
