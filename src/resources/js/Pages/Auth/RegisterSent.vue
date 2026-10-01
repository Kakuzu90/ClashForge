<script setup lang="ts">
import UiCard from '@/Components/ui/UiCard.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { login } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineOptions({ layout: PublicLayout });

type Props = App.Http.Data.Auth.RegisterSentPageData;

// Every registration ends here, whatever happened to the address (specs/11 "Account enumeration").
defineProps<{ linkMinutes: Props['linkMinutes'] }>();
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-col gap-6 py-6 md:py-12">
        <h1 class="font-display text-h1">Check your email</h1>

        <UiCard class="flex flex-col gap-3 p-5 text-body sm:p-6">
            <p>We sent an email to the address you entered. Open the link in it to confirm your account.</p>
            <p class="text-fg-secondary">The link expires in {{ linkMinutes }} minutes. Not there? Check your spam folder, or sign in and ask for a new one.</p>
        </UiCard>

        <p class="text-sm text-fg-secondary">
            <Link
                :href="login().url"
                class="font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                Go to sign in
            </Link>
        </p>
    </div>
</template>
