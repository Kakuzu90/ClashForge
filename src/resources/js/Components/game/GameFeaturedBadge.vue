<script setup lang="ts">
// FeaturedBadge: the purple star in the same chunky circle as the VerifiedBadge, so the two read as a
// pair on cards. The label says what it means, for screen readers and as the hover tooltip.
withDefaults(defineProps<{ size?: 16 | 20 | 24 }>(), { size: 20 });

const label = "Featured: the account shown first on its owner's profile";
</script>

<template>
    <span role="img" :aria-label="label" :title="label" class="inline-flex shrink-0">
        <svg :width="size" :height="size" viewBox="0 0 20 20" aria-hidden="true">
            <circle cx="10" cy="10" r="8.5" class="fill-featured stroke-accent-shadow" stroke-width="1.5" />
            <path d="M10 4.6l1.5 3.2 3.5.4-2.6 2.4.7 3.5L10 12.4l-3.1 1.7.7-3.5L5 8.2l3.5-.4z" class="fill-fg" />
        </svg>
    </span>
</template>
