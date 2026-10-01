<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import { formatDateTime } from '@/Composables/useDateTime';
import { destroy, destroyOthers } from '@/routes/settings/security/sessions';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Session = App.Domain.Auth.Data.SessionData;

// FR-AUTH-7: every signed-in browser, this one first. Signing one out asks first, since it ends
// that browser's remember-me too. The list arrives as a deferred prop; the page shows the skeleton.
const props = defineProps<{ sessions: Session[] }>();

const others = computed(() => props.sessions.filter((session) => !session.isCurrent));

// null: the "all other devices" dialog; a session: that one device.
const pending = ref<Session | null | undefined>(undefined);
const open = computed({
    get: () => pending.value !== undefined,
    set: (value: boolean) => {
        if (!value) {
            pending.value = undefined;
        }
    },
});
const working = ref(false);

const dialogTitle = computed(() => (pending.value ? `Sign out ${pending.value.deviceLabel}?` : 'Sign out every other device?'));
const dialogBody = computed(() =>
    pending.value
        ? 'That device will need your password to sign in again.'
        : `${others.value.length === 1 ? 'The other device' : `All ${others.value.length} other devices`} will need your password to sign in again. This one stays signed in.`,
);

function where(session: Session): string {
    return session.country ? `${session.country} · ` : '';
}

function confirmSignOut() {
    const url = pending.value ? destroy(pending.value.key).url : destroyOthers().url;

    router.delete(url, {
        preserveScroll: true,
        onStart: () => (working.value = true),
        onFinish: () => {
            working.value = false;
            pending.value = undefined;
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <ul class="flex flex-col divide-y divide-line-subtle rounded-lg border border-line">
            <li v-for="session in sessions" :key="session.key" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <p class="flex flex-wrap items-center gap-2 text-body font-semibold text-fg">
                        {{ session.deviceLabel }}
                        <span v-if="session.isCurrent" class="rounded-sm border border-success px-2 text-xs text-fg uppercase">This device</span>
                    </p>
                    <p class="text-sm text-fg-secondary">
                        {{ where(session) }}<template v-if="session.isCurrent">Active now</template
                        ><template v-else>Last active <time :datetime="session.lastActiveAt">{{ formatDateTime(session.lastActiveAt) }}</time></template>
                    </p>
                </div>
                <UiButton v-if="!session.isCurrent" variant="secondary" size="sm" @click="pending = session">Sign out</UiButton>
            </li>
        </ul>

        <p v-if="others.length === 0" class="text-sm text-fg-secondary">You are only signed in on this device.</p>
        <div v-else>
            <UiButton variant="danger" size="sm" @click="pending = null">Sign out every other device</UiButton>
        </div>

        <UiModal v-model:open="open" :title="dialogTitle" :description="dialogBody">
            <div class="flex flex-wrap justify-end gap-3">
                <UiButton variant="ghost" @click="open = false">Cancel</UiButton>
                <UiButton variant="danger" :loading="working" @click="confirmSignOut">Sign out</UiButton>
            </div>
        </UiModal>
    </div>
</template>
