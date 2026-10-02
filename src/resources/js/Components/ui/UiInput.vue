<script setup lang="ts">
import { computed, onMounted, ref, useAttrs, useId } from 'vue';

// class/style go to the wrapper, every other attribute to the <input>.
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        label: string;
        type?: 'text' | 'email' | 'password' | 'search' | 'url' | 'tel' | 'number' | 'date';
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
        /** Focuses the field on mount; native `autofocus` alone is skipped on Inertia visits. */
        autofocus?: boolean;
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

const inputEl = ref<HTMLInputElement | null>(null);

// Password fields get a show/hide toggle; the value never leaves the input either way.
const revealed = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type));
onMounted(() => {
    if (props.autofocus) inputEl.value?.focus();
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
                ref="inputEl"
                v-model="model"
                :type="inputType"
                :maxlength="maxlength"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :autofocus="autofocus"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="h-full w-full min-w-0 bg-transparent px-3 text-body text-fg outline-none placeholder:text-fg-muted disabled:cursor-not-allowed"
                v-bind="controlAttrs"
            />
            <span v-if="suffix" :id="ids.suffix" class="pr-3 text-sm text-fg-muted">{{ suffix }}</span>
            <button
                v-if="isPassword"
                type="button"
                class="hit-target mr-1 inline-flex size-9 shrink-0 items-center justify-center rounded-sm text-fg-muted hover:bg-surface-raised hover:text-fg disabled:cursor-not-allowed"
                :aria-label="revealed ? 'Hide password' : 'Show password'"
                :aria-pressed="revealed ? 'true' : 'false'"
                :aria-controls="inputId"
                :disabled="disabled"
                @click="revealed = !revealed"
            >
                <svg v-if="revealed" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true">
                    <path
                        d="M3 3l14 14M8.5 8.6a2 2 0 0 0 2.9 2.8M6.3 5.6C4.4 6.7 3 8.4 2 10c1.6 2.9 4.6 5.5 8 5.5 1.4 0 2.7-.4 3.8-1.1M9 4.6c.3 0 .7-.1 1-.1 3.4 0 6.4 2.6 8 5.5-.5.9-1.1 1.8-1.9 2.6"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <svg v-else width="18" height="18" viewBox="0 0 20 20" aria-hidden="true">
                    <path
                        d="M2 10c1.6-2.9 4.6-5.5 8-5.5s6.4 2.6 8 5.5c-1.6 2.9-4.6 5.5-8 5.5S3.6 12.9 2 10z"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                        stroke-linejoin="round"
                    />
                    <circle cx="10" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6" />
                </svg>
            </button>
        </div>
        <p v-if="error" :id="ids.error" class="text-sm text-danger-fg">{{ error }}</p>
        <div v-if="hint || (counter && maxlength)" class="flex justify-between gap-2 text-sm text-fg-muted">
            <p v-if="hint" :id="ids.hint">{{ hint }}</p>
            <p v-if="counter && maxlength" :id="ids.counter" class="ml-auto tabular-nums">{{ model.length }}/{{ maxlength }}</p>
        </div>
    </div>
</template>
