<script setup lang="ts">
// The XP level in our own twelve-point burst (a genre convention, drawn here, not traced). Used by
// the account profile panel and the list PlayerCard in place of the Town Hall badge.
withDefaults(defineProps<{ level: number | null; size?: 'md' | 'lg' }>(), { size: 'lg' });

const points = Array.from({ length: 24 }, (_, i) => {
    const r = i % 2 === 0 ? 30 : 24;
    const a = (Math.PI / 12) * i - Math.PI / 2;

    return `${(32 + r * Math.cos(a)).toFixed(2)},${(32 + r * Math.sin(a)).toFixed(2)}`;
}).join(' ');
</script>

<template>
    <span
        class="relative grid shrink-0 place-items-center"
        :class="size === 'lg' ? 'size-16' : 'size-12'"
        :title="level === null ? undefined : `XP level ${level}`"
    >
        <svg class="absolute inset-0 size-full" viewBox="0 0 64 64" aria-hidden="true">
            <polygon :points="points" class="fill-info stroke-line-strong" stroke-width="2" stroke-linejoin="round" />
        </svg>
        <span class="relative font-display text-body text-fg-inverse tabular-nums"> <span class="sr-only">XP level </span>{{ level ?? '?' }} </span>
    </span>
</template>
