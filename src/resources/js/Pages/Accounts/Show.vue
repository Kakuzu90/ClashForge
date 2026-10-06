<script setup lang="ts">
import GameAccountProfile from '@/Components/game/GameAccountProfile.vue';
import GameVillageBase from '@/Components/game/GameVillageBase.vue';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiTabs from '@/Components/ui/UiTabs.vue';
import { formatDuration } from '@/Composables/useDateTime';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { usePageProps } from '@/Composables/usePageProps';
import AppLayout from '@/Layouts/AppLayout.vue';
import { destroy, featured, verify } from '@/routes/accounts';
import { show as disputeShow } from '@/routes/disputes';
import { Deferred, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

// The CoC account page (specs/18 §6), following the game's profile screen: a Home Village and a
// Builder Base tab, each with the same profile panel (only the ranked data differs) and its base
// panel. Everything renders from stored data, so
// it works while the game API is down (FR-COC-14); the grids load as a deferred prop behind their
// skeleton.
const props = defineProps<{
    account: App.Domain.PlayerAccounts.Data.AccountDetailData;
    progression?: App.Domain.PlayerAccounts.Data.ProgressionGroupData[];
}>();

const { cocApi } = usePageProps();
const card = computed(() => props.account.card);
const age = computed(() => (card.value.syncedAgeSeconds === null ? null : formatDuration(card.value.syncedAgeSeconds)));
const deltaLabel = computed(() => `Change over the last ${props.account.deltaDays} days`);
const builderRanked = computed(() => ({
    league: props.account.builderLeague,
    leagueName: props.account.builderLeagueName,
    trophies: 'builder_trophies',
    best: 'best_builder_trophies',
}));

const villages = [
    { key: 'home', label: 'Home Village' },
    { key: 'builder', label: 'Builder Base' },
];
const village = ref('home');
const groups = (wanted: App.Domain.GameAssets.Enums.Village) => (props.progression ?? []).filter((g) => g.village === wanted);

// The owner's actions (P2-14). The server decides who may: these only follow its flags.
const featureForm = useForm({});
function makeFeatured() {
    featureForm.put(featured(card.value.ulid).url, { preserveScroll: true });
}

const removing = ref(false);
const removeWarning = computed(() => {
    const badge = card.value.status === 'verified' ? ' and loses its verified badge' : '';

    return `${card.value.tag} will no longer be on your Clash Commons account${badge}. Anyone with its in-game API token can verify it, you included. Its game history stays.`;
});
const removeForm = useForm({ current_password: '' });
const removeEl = ref<HTMLFormElement | null>(null);
function remove() {
    removeForm.delete(destroy(card.value.ulid).url, {
        onFinish: () => removeForm.reset('current_password'),
        onError: () => focusFirstError(removeEl.value),
    });
}
</script>

<template>
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 sm:py-8">
        <!-- Each tab shows the name in its own panel, as the game does; this stays the page heading. -->
        <h1 class="sr-only">{{ card.name }} {{ card.tag }}</h1>
        <div v-if="cocApi || account.notFound || card.stale || card.status !== 'verified'" class="flex flex-col gap-3">
            <UiAlert v-if="cocApi" kind="warning" title="Game data is temporarily unavailable">
                {{ age === null ? 'Showing the last saved data.' : `Showing data from ${age} ago.` }}
            </UiAlert>
            <UiAlert v-if="account.notFound" kind="warning" title="Clash of Clans can't find this tag any more">
                It may have been renamed or deleted in game. The account stays verified.
            </UiAlert>
            <UiAlert v-else-if="card.stale && !cocApi" kind="warning" title="This data is out of date">
                {{ age === null ? 'This account has not been synced yet.' : `It was last updated ${age} ago.` }}
            </UiAlert>
            <UiAlert v-if="card.status === 'disputed'" kind="info" title="Ownership is under review">
                Someone has asked the admins to review who owns this account. It stays with its current owner until they decide.
                <template v-if="account.canVerify">If it is yours, verifying it again with the in-game API token ends the review.</template>
                <div v-if="account.disputeUlid || account.canVerify" class="mt-3 flex flex-wrap gap-2">
                    <UiButton v-if="account.canVerify" :href="verify(card.ulid).url" size="sm">Verify this account</UiButton>
                    <UiButton v-if="account.disputeUlid" :href="disputeShow(account.disputeUlid).url" size="sm" variant="secondary">
                        See the dispute
                    </UiButton>
                </div>
            </UiAlert>
            <UiAlert v-if="card.status === 'suspended'" kind="danger" title="This account is suspended">
                The admins have suspended this account on Clash Commons. Only you can see it, and nobody can verify it until they release it.
            </UiAlert>
            <UiAlert v-if="card.status === 'unverified' && account.isOwn" kind="info" title="This account is not verified yet">
                Only you can see it until you verify it with the in-game API token.
                <div v-if="account.canVerify" class="mt-3">
                    <UiButton :href="verify(card.ulid).url" size="sm">Verify this account</UiButton>
                </div>
            </UiAlert>
        </div>

        <UiTabs v-model="village" :tabs="villages" label="Village" variant="pill">
            <template #home>
                <div class="flex flex-col gap-6">
                    <GameAccountProfile :card="card" :stats="account.stats" :delta-label="deltaLabel" />
                    <Deferred data="progression">
                        <template #fallback>
                            <GameVillageBase :hall="card.townHall" :groups="null" :side="['heroes', 'equipment', 'pets']" />
                        </template>
                        <GameVillageBase :hall="card.townHall" :groups="groups('home')" :side="['heroes', 'equipment', 'pets']" />
                    </Deferred>
                </div>
            </template>
            <template #builder>
                <div class="flex flex-col gap-6">
                    <GameAccountProfile :card="card" :stats="account.stats" :delta-label="deltaLabel" :ranked="builderRanked" />
                    <Deferred data="progression">
                        <template #fallback>
                            <GameVillageBase :hall="account.builderHall" :groups="null" :side="['builder_heroes']" />
                        </template>
                        <GameVillageBase :hall="account.builderHall" :groups="groups('builderBase')" :side="['builder_heroes']" />
                    </Deferred>
                </div>
            </template>
        </UiTabs>

        <footer class="flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-fg-muted">{{ age === null ? 'Not synced yet.' : `Game data updated ${age} ago.` }}</p>
            <div v-if="account.canFeature || account.canDetach" class="flex flex-col gap-2 sm:flex-row">
                <UiButton v-if="account.canFeature" size="sm" variant="secondary" :loading="featureForm.processing" @click="makeFeatured">
                    Make featured
                </UiButton>
                <UiButton v-if="account.canDetach" size="sm" variant="ghost" @click="removing = true">Remove account</UiButton>
            </div>
        </footer>

        <UiModal v-if="account.canDetach" v-model:open="removing" title="Remove this account?">
            <form ref="removeEl" class="flex flex-col gap-4" novalidate @submit.prevent="remove">
                <p class="text-body text-fg-secondary">{{ removeWarning }}</p>
                <UiInput
                    v-model="removeForm.current_password"
                    label="Current password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="removeForm.errors.current_password"
                    :disabled="removeForm.processing"
                />
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UiButton variant="secondary" :disabled="removeForm.processing" @click="removing = false">Cancel</UiButton>
                    <UiButton type="submit" variant="danger" :loading="removeForm.processing">Remove account</UiButton>
                </div>
            </form>
        </UiModal>
    </div>
</template>
