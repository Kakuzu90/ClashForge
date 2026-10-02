<script setup lang="ts">
// Progress through a short, fixed flow (specs/18 §6 attach flow). `current` is 1-based; earlier
// steps read as done, the current one carries aria-current.
const props = defineProps<{ steps: string[]; current: number; label?: string }>();

function state(index: number): 'done' | 'current' | 'upcoming' {
    const step = index + 1;
    return step < props.current ? 'done' : step === props.current ? 'current' : 'upcoming';
}
</script>

<template>
    <ol :aria-label="label ?? 'Progress'" class="flex items-start gap-2">
        <li
            v-for="(step, index) in steps"
            :key="step"
            class="flex min-w-0 flex-1 flex-col gap-2"
            :aria-current="state(index) === 'current' ? 'step' : undefined"
        >
            <span
                class="h-1.5 rounded-full"
                :class="state(index) === 'upcoming' ? 'bg-line' : 'bg-brand'"
                aria-hidden="true"
            />
            <span class="text-sm" :class="state(index) === 'current' ? 'font-semibold text-fg' : 'text-fg-muted'">
                <span class="sr-only">{{ state(index) === 'done' ? 'Done: ' : state(index) === 'current' ? 'Current: ' : '' }}</span>
                {{ index + 1 }}. {{ step }}
            </span>
        </li>
    </ol>
</template>
