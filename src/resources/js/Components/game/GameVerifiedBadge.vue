<script setup lang="ts">
// VerifiedBadge (specs/18 §4): a gold check in a chunky circle. The label says what verification
// means, for screen readers and as the hover tooltip; never rendered without it. A profile's cover
// band passes its own label, since there it marks the person, not one account (P2-22).
withDefaults(defineProps<{ size?: 16 | 20 | 24; label?: string }>(), {
    size: 20,
    label: 'Verified: the owner proved it with the in-game API token',
});
</script>

<template>
    <span role="img" :aria-label="label" :title="label" class="inline-flex shrink-0">
        <svg :width="size" :height="size" viewBox="0 0 20 20" aria-hidden="true">
            <circle cx="10" cy="10" r="8.5" class="fill-verified stroke-brand-shadow" stroke-width="1.5" />
            <path d="M6.2 10.3l2.5 2.5 5-5.3" fill="none" class="stroke-fg-on-gold" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
</template>
