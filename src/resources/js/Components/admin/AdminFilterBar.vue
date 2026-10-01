<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';

// specs/18 §4 FilterBar: the fields go in the default slot; Apply submits, Clear resets. A plain
// GET form underneath, so Enter in any field applies the filters.
withDefaults(defineProps<{ label: string; busy?: boolean; canClear?: boolean }>(), { busy: false, canClear: false });

const emit = defineEmits<{ apply: []; clear: [] }>();
</script>

<template>
    <form
        role="search"
        :aria-label="label"
        class="flex flex-col gap-3 rounded-sm border border-line bg-surface p-3"
        novalidate
        @submit.prevent="emit('apply')"
    >
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <slot />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <UiButton type="submit" size="sm" :loading="busy">Apply filters</UiButton>
            <UiButton v-if="canClear" variant="ghost" size="sm" :disabled="busy" @click="emit('clear')">Clear</UiButton>
        </div>
    </form>
</template>
