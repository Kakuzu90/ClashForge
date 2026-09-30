<script setup lang="ts">
import { computed, useAttrs, useId } from 'vue';

// class/style go to the wrapper, every other attribute to the <textarea>.
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        label: string;
        hint?: string;
        error?: string;
        maxlength?: number;
        counter?: boolean;
        rows?: number;
        disabled?: boolean;
        readonly?: boolean;
        required?: boolean;
        id?: string;
    }>(),
    { rows: 4 },
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
const errorId = computed(() => `${inputId.value}-error`);
const hintId = computed(() => `${inputId.value}-hint`);
const counterId = computed(() => `${inputId.value}-counter`);

const describedBy = computed(
    () =>
        [props.error ? errorId.value : null, props.hint ? hintId.value : null, props.counter && props.maxlength ? counterId.value : null]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="flex flex-col gap-1" v-bind="wrapperAttrs">
        <label :for="inputId" class="text-sm font-medium text-fg-secondary">
            {{ label }}<span v-if="required" class="text-danger-fg" aria-hidden="true"> *</span>
        </label>
        <textarea
            :id="inputId"
            v-model="model"
            :rows="rows"
            :maxlength="maxlength"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            class="w-full rounded-sm border px-3 py-2 text-body text-fg placeholder:text-fg-muted disabled:cursor-not-allowed disabled:opacity-60"
            :class="[error ? 'border-danger' : 'border-control', readonly ? 'bg-page' : 'bg-surface']"
            v-bind="controlAttrs"
        />
        <p v-if="error" :id="errorId" class="text-sm text-danger-fg">{{ error }}</p>
        <div v-if="hint || (counter && maxlength)" class="flex justify-between gap-2 text-sm text-fg-muted">
            <p v-if="hint" :id="hintId">{{ hint }}</p>
            <p v-if="counter && maxlength" :id="counterId" class="ml-auto tabular-nums">{{ model.length }}/{{ maxlength }}</p>
        </div>
    </div>
</template>
