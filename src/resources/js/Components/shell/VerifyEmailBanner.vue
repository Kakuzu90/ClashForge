<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { notice } from '@/routes/verification';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

// FR-AUTH-4: a signed-in account that has not confirmed its email sees this at the top of each
// app page, with the way to a fresh link. Hidden on the confirm page itself.
const { auth, url } = usePageProps();
const show = computed(() => !!auth.value?.user && !auth.value.user.emailVerified && url.value.split(/[?#]/)[0] !== notice().url);
</script>

<template>
    <UiAlert v-if="show" kind="info" title="Confirm your email">
        <p class="text-sm">
            Until you do, you cannot upload, post or link a Clash of Clans account.
            <Link
                :href="notice().url"
                class="font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >Resend the link</Link
            >
        </p>
    </UiAlert>
</template>
