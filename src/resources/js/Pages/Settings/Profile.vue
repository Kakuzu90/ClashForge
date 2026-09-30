<script setup lang="ts">
import SettingsAvatarField from '@/Components/settings/SettingsAvatarField.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiSelect from '@/Components/ui/UiSelect.vue';
import UiTextarea from '@/Components/ui/UiTextarea.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { update } from '@/routes/settings/profile';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

type Props = App.Http.Data.Settings.ProfileSettingsPageData;

const props = defineProps<{
    profile: Props['profile'];
    avatarUpload: Props['avatarUpload'];
    countries: Props['countries'];
    languages: Props['languages'];
    timezones: Props['timezones'];
    limits: Props['limits'];
}>();

// "Not set" is a real option (value '') so a chosen value can be cleared; the server stores it as null.
const none = { value: '', label: 'Not set' };
const countryOptions = computed(() => [none, ...props.countries]);
const timezoneOptions = computed(() => [none, ...props.timezones]);
const languageOptions = computed(() => [none, ...props.languages]);

// One select per language slot keeps the form keyboard-simple; empty slots are dropped on save.
const languageSlots = ref<string[]>(Array.from({ length: props.limits.languagesMax }, (_, i) => props.profile.languages[i] ?? ''));
const slotLabels = ['Main language', 'Second language', 'Third language'];

// The @ is shown as an input prefix, so the field holds the handle without it.
const withoutAt = (handle: string | null) => (handle ?? '').replace(/^@/, '');

const form = useForm({
    display_name: props.profile.displayName ?? '',
    bio: props.profile.bio ?? '',
    country_code: props.profile.countryCode ?? '',
    languages: props.profile.languages,
    timezone: props.profile.timezone ?? '',
    socials: {
        youtube: withoutAt(props.profile.socials.youtube),
        twitch: props.profile.socials.twitch ?? '',
        x: withoutAt(props.profile.socials.x),
        discord: props.profile.socials.discord ?? '',
    },
});

const formEl = ref<HTMLFormElement | null>(null);
const avatarName = computed(() => props.profile.displayName || props.profile.username);
const errors = computed(() => form.errors as Record<string, string | undefined>);

function submit() {
    form.transform((data) => ({ ...data, languages: languageSlots.value.filter((code) => code !== '') })).patch(update().url, {
        preserveScroll: true,
        onError: () => focusFirstError(formEl.value),
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <h2 class="sr-only">Profile</h2>

        <UiCard variant="flat" class="p-4 sm:p-6">
            <SettingsAvatarField :name="avatarName" :avatar="profile.avatar" :rules="avatarUpload" :error="errors.media" />
        </UiCard>

        <UiCard variant="flat" class="p-4 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                <UiInput
                    v-model="form.display_name"
                    label="Display name"
                    :hint="`Shown instead of @${profile.username}. Leave empty to use your username.`"
                    :maxlength="limits.displayNameMax"
                    autocomplete="nickname"
                    :error="form.errors.display_name"
                />
                <UiTextarea v-model="form.bio" label="Bio" :maxlength="limits.bioMax" counter :rows="4" :error="form.errors.bio" />

                <div class="grid gap-5 sm:grid-cols-2">
                    <UiSelect
                        v-model="form.country_code"
                        label="Country"
                        searchable
                        :options="countryOptions"
                        :error="form.errors.country_code"
                    />
                    <UiSelect
                        v-model="form.timezone"
                        label="Timezone"
                        searchable
                        :options="timezoneOptions"
                        :error="form.errors.timezone"
                    />
                </div>

                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-1 text-body font-semibold text-fg">Languages</legend>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <UiSelect
                            v-for="(_, index) in languageSlots"
                            :key="index"
                            v-model="languageSlots[index]"
                            :label="slotLabels[index] ?? `Language ${index + 1}`"
                            searchable
                            :options="languageOptions"
                        />
                    </div>
                    <p v-if="form.errors.languages" role="alert" class="text-sm text-danger-fg">{{ form.errors.languages }}</p>
                </fieldset>

                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-1 text-body font-semibold text-fg">Social handles</legend>
                    <p class="text-sm text-fg-secondary">Handles only, not links. Your profile links to them for you.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <UiInput v-model="form.socials.youtube" label="YouTube" prefix="@" :error="errors['socials.youtube']" />
                        <UiInput v-model="form.socials.twitch" label="Twitch" :error="errors['socials.twitch']" />
                        <UiInput v-model="form.socials.x" label="X" prefix="@" :error="errors['socials.x']" />
                        <UiInput v-model="form.socials.discord" label="Discord" :error="errors['socials.discord']" />
                    </div>
                </fieldset>

                <div class="flex items-center gap-3">
                    <UiButton type="submit" :loading="form.processing">Save profile</UiButton>
                    <span v-if="form.recentlySuccessful" role="status" class="text-sm text-fg-secondary">Saved.</span>
                </div>
            </form>
        </UiCard>
    </div>
</template>
