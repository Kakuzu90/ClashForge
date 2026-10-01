<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCheckbox from '@/Components/ui/UiCheckbox.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { destroy } from '@/routes/settings/danger-zone';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

type Props = App.Http.Data.Settings.DangerZonePageData;
defineProps<{ graceDays: Props['graceDays']; canRequestDeletion: Props['canRequestDeletion'] }>();

const form = useForm({ current_password: '', confirmation: false });
const formEl = ref<HTMLFormElement | null>(null);

function submit() {
    form.delete(destroy().url, {
        onFinish: () => form.reset('current_password'),
        onError: () => focusFirstError(formEl.value),
    });
}
</script>

<template>
    <section class="flex flex-col gap-5 rounded-lg border border-danger bg-surface p-4 sm:p-6" aria-labelledby="deletion-title">
        <div class="flex flex-col gap-2">
            <h2 id="deletion-title" class="text-h3 font-semibold">Delete your account</h2>
            <p class="text-body text-fg-secondary">
                Your profile will be hidden and every device signed out. Sign in again within {{ graceDays }} days to cancel deletion.
            </p>
            <p class="text-body text-fg-secondary">
                After {{ graceDays }} days, your profile, avatar and notifications will be removed and your account anonymised. Your username stays
                reserved. Moderation and audit records are kept. Registering again will not restore your data.
            </p>
        </div>

        <form v-if="canRequestDeletion" ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <UiInput
                v-model="form.current_password"
                label="Current password"
                type="password"
                autocomplete="current-password"
                required
                :error="form.errors.current_password"
                :disabled="form.processing"
            />
            <UiCheckbox
                v-model="form.confirmation"
                label="I understand what account deletion removes."
                :error="form.errors.confirmation"
                :disabled="form.processing"
            />
            <div>
                <UiButton type="submit" variant="danger" :loading="form.processing">Request account deletion</UiButton>
            </div>
        </form>
        <p v-else role="status" class="text-body text-fg-secondary">Account deletion is unavailable while your account is suspended.</p>
    </section>
</template>
