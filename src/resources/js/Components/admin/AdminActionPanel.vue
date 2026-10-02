<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiSelect, { type SelectOption } from '@/Components/ui/UiSelect.vue';
import UiTextarea from '@/Components/ui/UiTextarea.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { useToast } from '@/Composables/useToast';
import { store as banStore } from '@/routes/admin/users/ban';
import { destroy as sanctionDestroy } from '@/routes/admin/users/sanction';
import { store as suspensionStore } from '@/routes/admin/users/suspension';
import type { Page } from '@inertiajs/core';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Kind = 'suspend' | 'ban' | 'lift';

// specs/18 §4 ActionPanel and specs/12 §4: every action asks for a reason and an internal note,
// shows what the account holder will see, and confirms. Buttons follow the server's flags; the
// server re-checks.
const props = defineProps<{
    ulid: string;
    username: string;
    abilities: App.Domain.Moderation.Data.SanctionAbilitiesData;
    options: App.Http.Data.Admin.SanctionFormData;
}>();

const { push } = useToast();

const kind = ref<Kind | null>(null);
const open = computed({
    get: () => kind.value !== null,
    set: (value: boolean) => {
        if (!value) kind.value = null;
    },
});

const form = useForm({ reason_code: null as string | null, days: '7', public_reason: '', internal_note: '' });
const liftForm = useForm({ note: '' });

const reasons = computed<SelectOption[]>(() => props.options.reasons);
const activeLabel = computed(() => (props.abilities.activeType === 'ban' ? 'ban' : 'suspension'));
const days = computed(() => Number.parseInt(form.days, 10));
const endsAt = computed(() =>
    Number.isInteger(days.value) && days.value > 0 ? formatDateTime(new Date(Date.now() + days.value * 86_400_000).toISOString()) : null,
);
const message = computed(() => form.public_reason.trim() || 'Your message appears here.');

const title = computed(() => {
    if (kind.value === 'suspend') return `Suspend ${props.username}`;
    if (kind.value === 'ban') return `Ban ${props.username}`;
    return `Lift the ${activeLabel.value}`;
});

function start(next: Kind) {
    form.clearErrors();
    liftForm.clearErrors();
    kind.value = next;
}

// A throttled write (global-write) redirects back with only `flash.error`, which Inertia counts as
// a success; the error toast says why, so the form stays open and no success toast is shown.
function done(page: Page, toast: string) {
    if ((page.props.flash as { error?: string | null } | undefined)?.error) {
        return;
    }
    kind.value = null;
    form.reset();
    liftForm.reset();
    push(toast, { kind: 'success' });
}

function submit() {
    const options = { preserveScroll: true };

    if (kind.value === 'suspend') {
        form.transform((data) => ({ ...data, days: Number.parseInt(data.days, 10) })).post(suspensionStore(props.ulid).url, {
            ...options,
            onSuccess: (page) => done(page, `${props.username} is suspended.`),
        });
    } else if (kind.value === 'ban') {
        form.transform(({ days: _days, ...data }) => data).post(banStore(props.ulid).url, {
            ...options,
            onSuccess: (page) => done(page, `${props.username} is banned.`),
        });
    } else if (kind.value === 'lift') {
        liftForm.delete(sanctionDestroy(props.ulid).url, {
            ...options,
            onSuccess: (page) => done(page, `The ${activeLabel.value} is lifted.`),
        });
    }
}

const busy = computed(() => form.processing || liftForm.processing);
const refusal = computed(
    () => (form.errors as Record<string, string | undefined>).sanction ?? (liftForm.errors as Record<string, string | undefined>).sanction,
);
</script>

<template>
    <div v-if="abilities.suspend || abilities.ban || abilities.lift" class="flex flex-wrap gap-2">
        <UiButton v-if="abilities.lift" variant="secondary" size="sm" @click="start('lift')">Lift the {{ activeLabel }}</UiButton>
        <UiButton v-if="abilities.suspend" variant="secondary" size="sm" @click="start('suspend')">Suspend</UiButton>
        <UiButton v-if="abilities.ban" variant="danger" size="sm" @click="start('ban')">Ban</UiButton>
    </div>
    <p v-else class="text-sm text-fg-secondary">You can't change this account's standing.</p>

    <UiModal v-model:open="open" :title="title">
        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <UiAlert v-if="refusal" kind="danger">{{ refusal }}</UiAlert>

            <template v-if="kind === 'suspend' || kind === 'ban'">
                <UiSelect
                    v-model="form.reason_code"
                    label="Reason"
                    :options="reasons"
                    placeholder="Choose a reason"
                    required
                    :error="form.errors.reason_code"
                />
                <UiInput
                    v-if="kind === 'suspend'"
                    v-model="form.days"
                    label="Length"
                    type="number"
                    suffix="days"
                    required
                    :hint="`1 to ${options.maxDays} days.`"
                    min="1"
                    :max="options.maxDays"
                    :error="form.errors.days"
                />
                <UiTextarea
                    v-model="form.public_reason"
                    :label="`Message to ${username}`"
                    hint="Shown to them on the notice and in the email."
                    :maxlength="options.publicReasonMax"
                    counter
                    :rows="2"
                    required
                    :error="form.errors.public_reason"
                />
                <UiTextarea
                    v-model="form.internal_note"
                    label="Internal note"
                    hint="Staff only. What happened and why."
                    :maxlength="options.noteMax"
                    :rows="3"
                    required
                    :error="form.errors.internal_note"
                />

                <section aria-label="What they will see" class="flex flex-col gap-1 rounded-sm border border-line bg-page p-3 text-sm">
                    <p class="font-semibold text-fg-secondary">What they will see</p>
                    <template v-if="kind === 'suspend'">
                        <p class="text-fg">Your account is suspended.</p>
                        <p class="text-fg">Reason: {{ message }}</p>
                        <p class="text-fg">Ends: {{ endsAt ?? 'pick a length' }}</p>
                    </template>
                    <template v-else>
                        <p class="text-fg">Your account is banned and can no longer sign in.</p>
                        <p class="text-fg">Reason: {{ message }}</p>
                        <p class="text-fg-secondary">Every device they are signed in on is signed out now.</p>
                    </template>
                </section>
            </template>

            <template v-else-if="kind === 'lift'">
                <UiTextarea
                    v-model="liftForm.note"
                    label="Why lift it"
                    hint="Staff only."
                    :maxlength="options.noteMax"
                    :rows="3"
                    required
                    :error="liftForm.errors.note"
                />
                <p class="text-sm text-fg-secondary">{{ username }} gets an email saying their {{ activeLabel }} has been lifted.</p>
            </template>

            <div class="flex flex-wrap justify-end gap-3">
                <UiButton variant="ghost" :disabled="busy" @click="open = false">Cancel</UiButton>
                <UiButton type="submit" :variant="kind === 'lift' ? 'primary' : 'danger'" :loading="busy">
                    <template v-if="kind === 'suspend'">Suspend for {{ Number.isInteger(days) && days > 0 ? days : '…' }} days</template>
                    <template v-else-if="kind === 'ban'">Ban {{ username }}</template>
                    <template v-else>Lift the {{ activeLabel }}</template>
                </UiButton>
            </div>
        </form>
    </UiModal>
</template>
