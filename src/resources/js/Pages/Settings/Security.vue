<script setup lang="ts">
import SettingsEmailSection from '@/Components/settings/SettingsEmailSection.vue';
import SettingsSessionList from '@/Components/settings/SettingsSessionList.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { update } from '@/routes/settings/security/password';
import { Deferred, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

type Props = App.Http.Data.Settings.SecuritySettingsPageData;

defineProps<{
    passwordMinLength: Props['passwordMinLength'];
    email: Props['email'];
    pendingEmail: Props['pendingEmail'];
    linkMinutes: Props['linkMinutes'];
    sessions?: App.Domain.Auth.Data.SessionData[];
}>();

const form = useForm({ current_password: '', password: '', password_confirmation: '' });
const formEl = ref<HTMLFormElement | null>(null);

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
    <div class="flex flex-col gap-6">
        <h2 class="sr-only">Security</h2>

        <UiCard variant="flat" class="p-4 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-5" novalidate @submit.prevent="submit">
                <div class="flex flex-col gap-1">
                    <h3 class="text-h3 text-fg">Password</h3>
                    <p class="text-sm text-fg-secondary">Changing it signs out every other device.</p>
                </div>
                <UiInput
                    v-model="form.current_password"
                    label="Current password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="form.errors.current_password"
                />
                <div class="grid gap-5 sm:grid-cols-2">
                    <UiInput
                        v-model="form.password"
                        label="New password"
                        type="password"
                        autocomplete="new-password"
                        required
                        :hint="`At least ${passwordMinLength} characters.`"
                        :error="form.errors.password"
                    />
                    <UiInput
                        v-model="form.password_confirmation"
                        label="Repeat new password"
                        type="password"
                        autocomplete="new-password"
                        required
                        :error="form.errors.password_confirmation"
                    />
                </div>
                <div class="flex items-center gap-3">
                    <UiButton type="submit" :loading="form.processing">Change password</UiButton>
                    <span v-if="form.recentlySuccessful" role="status" class="text-sm text-fg-secondary">Saved.</span>
                </div>
            </form>
        </UiCard>

        <UiCard variant="flat" class="p-4 sm:p-6">
            <SettingsEmailSection :email="email" :pending-email="pendingEmail" :link-minutes="linkMinutes" />
        </UiCard>

        <UiCard variant="flat" class="p-4 sm:p-6">
            <section aria-labelledby="sessions-heading" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <h3 id="sessions-heading" class="text-h3 text-fg">Where you're signed in</h3>
                    <p class="text-sm text-fg-secondary">If you don't recognise a device, sign it out and change your password.</p>
                </div>
                <Deferred data="sessions">
                    <template #fallback>
                        <div class="flex flex-col gap-4 rounded-lg border border-line p-4">
                            <UiSkeleton :lines="2" label="Loading your sessions" />
                            <UiSkeleton :lines="2" />
                        </div>
                    </template>
                    <SettingsSessionList :sessions="sessions ?? []" />
                </Deferred>
            </section>
        </UiCard>
    </div>
</template>
