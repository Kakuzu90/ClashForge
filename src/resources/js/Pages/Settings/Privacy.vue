<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiRadioGroup from '@/Components/ui/UiRadioGroup.vue';
import UiToggle from '@/Components/ui/UiToggle.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { show } from '@/routes/profile';
import { update } from '@/routes/settings/privacy';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

type Props = App.Http.Data.Settings.PrivacySettingsPageData;

const props = defineProps<{
    settings: Props['settings'];
    visibilityOptions: Props['visibilityOptions'];
    username: Props['username'];
}>();

const form = useForm({
    profile_visibility: props.settings.visibility as string,
    show_coc_accounts: props.settings.showCocAccounts,
    show_clan: props.settings.showClan,
    allow_recruitment_contact: props.settings.allowRecruitmentContact,
    searchable: props.settings.searchable,
});

const formEl = ref<HTMLFormElement | null>(null);

function submit() {
    form.patch(update().url, {
        preserveScroll: true,
        onError: () => focusFirstError(formEl.value),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <h2 class="sr-only">Privacy</h2>

        <UiCard variant="flat" class="p-4 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
                <UiRadioGroup
                    v-model="form.profile_visibility"
                    legend="Who can see your profile"
                    :options="visibilityOptions"
                    :error="form.errors.profile_visibility"
                />

                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-body font-semibold text-fg">On your profile</legend>
                    <UiToggle
                        v-model="form.show_coc_accounts"
                        label="Show my Clash of Clans accounts"
                        hint="Applies once account linking opens."
                        :error="form.errors.show_coc_accounts"
                    />
                    <UiToggle
                        v-model="form.show_clan"
                        label="Show my clan"
                        hint="Applies once account linking opens."
                        :error="form.errors.show_clan"
                    />
                </fieldset>

                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-body font-semibold text-fg">Contact and search</legend>
                    <UiToggle
                        v-model="form.allow_recruitment_contact"
                        label="Let clans contact me about recruitment"
                        :error="form.errors.allow_recruitment_contact"
                    />
                    <UiToggle
                        v-model="form.searchable"
                        label="Show my profile in search results"
                        hint="Search engines only list it while everyone can see your profile."
                        :error="form.errors.searchable"
                    />
                </fieldset>

                <div class="flex flex-wrap items-center gap-3">
                    <UiButton type="submit" :loading="form.processing">Save privacy settings</UiButton>
                    <Link
                        :href="show(username).url"
                        class="ml-auto inline-flex min-h-11 items-center text-sm font-semibold text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        View your profile
                    </Link>
                </div>
            </form>
        </UiCard>
    </div>
</template>
