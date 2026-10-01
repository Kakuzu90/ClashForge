<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { update } from '@/routes/settings/profile/username';
import { notice } from '@/routes/verification';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// FR-PROFILE-7: whether a change is open is the server's answer (`canChange`); this only picks
// which explanation to show.
const props = defineProps<{ settings: App.Domain.Auth.Data.UsernameSettingsData }>();

const form = useForm({ username: '', current_password: '' });
const formEl = ref<HTMLFormElement | null>(null);
const nextChange = computed(() => (props.settings.nextChangeAt ? formatDateTime(props.settings.nextChangeAt) : null));

function submit() {
    form.put(update().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            form.reset('current_password');
            focusFirstError(formEl.value);
        },
    });
}
</script>

<template>
    <section aria-labelledby="username-heading" class="flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h3 id="username-heading" class="text-h3 text-fg">Username</h3>
            <p class="text-sm text-fg-secondary">
                You are <span class="font-semibold break-all text-fg">@{{ settings.username }}</span
                >. You can change it once every {{ settings.changeDays }} days. Your old username stays yours for {{ settings.reservationDays }} days, and
                links to it lead to your profile until then.
            </p>
        </div>

        <form v-if="settings.canChange" ref="formEl" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
            <UiInput
                v-model="form.username"
                label="New username"
                prefix="@"
                :hint="`${settings.minLength} to ${settings.maxLength} lowercase letters, numbers or underscores.`"
                :maxlength="settings.maxLength"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                required
                :error="form.errors.username"
            />
            <UiInput
                v-model="form.current_password"
                label="Current password"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.current_password"
            />
            <div class="flex items-center gap-3">
                <UiButton type="submit" :loading="form.processing">Change username</UiButton>
            </div>
        </form>

        <p v-else-if="settings.needsVerifiedEmail" class="text-body text-fg" data-test="username-unverified">
            Confirm your email address before changing your username.
            <Link
                :href="notice().url"
                class="font-semibold text-brand underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                Send the confirmation email
            </Link>
        </p>

        <p v-else-if="nextChange" class="text-body text-fg" data-test="username-locked">You can change your username again on {{ nextChange }}.</p>

        <p v-else role="status" class="text-body text-fg-secondary" data-test="username-unavailable">
            Username changes are unavailable while your account is suspended.
        </p>
    </section>
</template>
