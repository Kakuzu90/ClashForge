<script setup lang="ts">
// Where the in-game API token lives (specs/09 §9). Numbered text steps; original illustrations
// are a later design item (owner decision 2026-10-02, P2-11).
const steps = [
    'Open Clash of Clans on the device that has this account.',
    'Tap Settings, then More Settings.',
    'Scroll to API Token, tap Show, then Copy.',
    'Come back here and paste it. It expires in a few minutes.',
];
</script>

<template>
    <ol class="flex flex-col gap-3" aria-label="How to get your API token">
        <li v-for="(step, index) in steps" :key="step" class="flex items-start gap-3 text-body text-fg">
            <span
                class="inline-flex size-7 shrink-0 items-center justify-center rounded-full border border-line bg-surface-raised text-sm font-semibold tabular-nums"
                aria-hidden="true"
            >
                {{ index + 1 }}
            </span>
            <span class="pt-0.5">{{ step }}</span>
        </li>
    </ol>
</template>
