<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiToggle from '@/Components/ui/UiToggle.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { useVisitError } from '@/Composables/useVisitError';
import AppLayout from '@/Layouts/AppLayout.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { update } from '@/routes/settings/notifications';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineOptions({ layout: [AppLayout, SettingsLayout] });

const props = defineProps<{ settings: App.Domain.Notifications.Data.EmailPreferencesData }>();
const form = useForm({
    email_enabled: props.settings.emailEnabled,
    email_categories: Object.fromEntries(
        props.settings.categories.filter((category) => !category.locked).map((category) => [category.key, category.enabled]),
    ) as Record<string, boolean>,
});
const formEl = ref<HTMLFormElement | null>(null);
const visitError = useVisitError();

function submit() {
    form.patch(update().url, { preserveScroll: true, onError: () => focusFirstError(formEl.value) });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <h2 class="text-body font-semibold">Email preferences</h2>
        <UiAlert v-if="visitError" kind="danger">We could not save your preferences. Try again.</UiAlert>
        <UiCard variant="flat" class="p-4 sm:p-6">
            <form ref="formEl" class="flex flex-col gap-6" novalidate @submit.prevent="submit">
                <UiToggle
                    v-model="form.email_enabled"
                    label="Non-security emails"
                    hint="Turn this off to stop all non-security emails. Your category choices will be kept."
                    :disabled="form.processing || !settings.canUpdate"
                    :error="form.errors.email_enabled"
                />
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-1 text-body font-semibold">Email categories</legend>
                    <template v-for="category in settings.categories" :key="category.key">
                        <div v-if="category.locked" class="flex min-h-11 items-center justify-between gap-4 text-body">
                            <span>{{ category.label }}</span>
                            <span class="text-sm text-fg-secondary">Always on</span>
                        </div>
                        <UiToggle
                            v-else
                            v-model="form.email_categories[category.key]"
                            :label="category.label"
                            :hint="category.hint ?? undefined"
                            :disabled="!form.email_enabled || form.processing || !settings.canUpdate"
                            :error="form.errors[`email_categories.${category.key}` as keyof typeof form.errors]"
                        />
                    </template>
                    <p v-if="form.errors.email_categories" role="alert" class="text-sm text-danger-fg">{{ form.errors.email_categories }}</p>
                    <p class="text-sm text-fg-secondary">
                        Accounts covers verified Clash of Clans accounts, and Bases covers failed uploads. Other categories apply as those features open.
                    </p>
                </fieldset>
                <div class="flex flex-wrap items-center gap-3">
                    <UiButton v-if="settings.canUpdate" type="submit" :loading="form.processing">Save email preferences</UiButton>
                </div>
            </form>
        </UiCard>
    </div>
</template>
