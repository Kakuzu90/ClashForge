<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue';

// class/style go to the wrapper, every other attribute to the <input>.
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        label: string;
        type?: 'text' | 'email' | 'password' | 'search' | 'url' | 'tel' | 'number';
        hint?: string;
        error?: string;
        prefix?: string;
        suffix?: string;
        maxlength?: number;
        /** Shows a live character counter; requires maxlength. */
        counter?: boolean;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        id?: string;
    }>(),
    { type: 'text' },
);

const model = defineModel<string>({ default: '' });

const attrs = useAttrs();
const wrapperAttrs = computed(() => ({ class: attrs.class, style: attrs.style }));
const controlAttrs = computed(() => {
    const { class: _class, style: _style, ...rest } = attrs;
    return rest;
});

const generatedId = useId();
const inputId = computed(() => props.id ?? generatedId);
const ids = computed(() => ({
    hint: `${inputId.value}-hint`,
    error: `${inputId.value}-error`,
    counter: `${inputId.value}-counter`,
    prefix: `${inputId.value}-prefix`,
    suffix: `${inputId.value}-suffix`,
}));

// Prefix/suffix (e.g. units) are announced as part of the description, not hidden.
const describedBy = computed(
    () =>
        [
            props.error ? ids.value.error : null,
            props.prefix ? ids.value.prefix : null,
            props.suffix ? ids.value.suffix : null,
            props.hint ? ids.value.hint : null,
            props.counter && props.maxlength ? ids.value.counter : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1" v-bind="wrapperAttrs">
        <label :for="inputId" class="text-sm font-medium text-fg-secondary">
            {{ label }}<span v-if="required" class="text-danger-fg" aria-hidden="true"> *</span>
        </label>
        <!-- 44px tall on touch-first widths (specs/18 §8), spec 18 §4's 40px from sm up. -->
        <div
            class="flex h-11 items-center rounded-sm border focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus sm:h-10"
            :class="[error ? 'border-danger' : 'border-control', disabled ? 'opacity-60' : '', readonly ? 'bg-page' : 'bg-surface']"
        >
            <span v-if="prefix" :id="ids.prefix" class="pl-3 text-sm text-fg-muted">{{ prefix }}</span>
            <input
                :id="inputId"
                v-model="model"
                :type="type"
                :maxlength="maxlength"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="h-full w-full min-w-0 bg-transparent px-3 text-body text-fg outline-none placeholder:text-fg-muted disabled:cursor-not-allowed"
                v-bind="controlAttrs"
            />
            <span v-if="suffix" :id="ids.suffix" class="pr-3 text-sm text-fg-muted">{{ suffix }}</span>
        </div>
        <p v-if="error" :id="ids.error" class="text-sm text-danger-fg">{{ error }}</p>
        <div v-if="hint || (counter && maxlength)" class="flex justify-between gap-2 text-sm text-fg-muted">
            <p v-if="hint" :id="ids.hint">{{ hint }}</p>
            <p v-if="counter && maxlength" :id="ids.counter" class="ml-auto tabular-nums">{{ model.length }}/{{ maxlength }}</p>
        </div>
    </div>
</template>
