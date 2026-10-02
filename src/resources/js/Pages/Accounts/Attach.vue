<script setup lang="ts">
import PlayerPreviewCard from '@/Components/accounts/PlayerPreviewCard.vue';
import TokenSteps from '@/Components/accounts/TokenSteps.vue';
import { verifyMessage, waitText } from '@/Components/accounts/attachMessages';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiSteps from '@/Components/ui/UiSteps.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { usePageProps } from '@/Composables/usePageProps';
import AppLayout from '@/Layouts/AppLayout.vue';
import { verify } from '@/routes/accounts';
import { preview as previewRoute, store, verifyTag } from '@/routes/accounts/attach';
import { notice } from '@/routes/verification';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Accounts.AttachPageData;
const props = defineProps<{ tag: Props['tag']; preview: Props['preview']; verifyResult: Props['verifyResult']; block: Props['block'] }>();

const lookup = useForm({ tag: props.tag ?? '' });
// The page stays mounted across lookups, so the tag is read from the current card at submit time.
const attach = useForm({ tag: '' });
const claim = useForm({ tag: '', api_token: '' });
const lookupEl = ref<HTMLFormElement | null>(null);
const claimEl = ref<HTMLFormElement | null>(null);

const outcome = computed(() => props.preview?.outcome ?? null);
const claimMessage = computed(() => verifyMessage(props.verifyResult));
// specs/13 §9: token verification is paused while the API is unavailable; lookup and attach are not.
const { cocApi } = usePageProps();

function submitLookup() {
    lookup.post(previewRoute().url, { preserveScroll: true, onError: () => focusFirstError(lookupEl.value) });
}

function submitAttach() {
    attach.tag = props.preview?.tag ?? '';
    attach.post(store().url, { preserveScroll: true });
}

function submitClaim() {
    if (cocApi.value) return;
    claim.tag = props.preview?.tag ?? '';
    // The token is good once and expires in minutes: never keep it in the field after a submit.
    claim.post(verifyTag().url, {
        preserveScroll: true,
        onError: () => focusFirstError(claimEl.value),
        onFinish: () => claim.reset('api_token'),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-4">
            <h1 class="font-display text-h1">Attach a Clash of Clans account</h1>
            <UiSteps :steps="['Find your account', 'Prove it is yours', 'Done']" :current="1" label="Attach steps" />
        </header>

        <UiAlert v-if="block === 'email_unverified'" kind="warning" title="Confirm your email first">
            You can attach accounts once your email address is confirmed.
            <Link :href="notice().url" class="font-medium text-brand underline underline-offset-4">Confirm your email</Link>
        </UiAlert>
        <UiAlert v-else-if="block === 'account_blocked'" kind="warning" title="Not available on your account right now">
            Your account cannot attach Clash of Clans accounts at the moment.
        </UiAlert>

        <template v-else>
            <UiCard class="p-4 sm:p-6">
                <form ref="lookupEl" class="flex flex-col gap-4" novalidate @submit.prevent="submitLookup">
                    <UiInput
                        v-model="lookup.tag"
                        label="Player tag"
                        hint="Find it in game under your name on your profile, for example #2PP0LJQ."
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        :maxlength="32"
                        required
                        :autofocus="!preview"
                        :error="lookup.errors.tag"
                    />
                    <UiButton type="submit" variant="secondary" :loading="lookup.processing">Find account</UiButton>
                </form>
            </UiCard>

            <section v-if="preview" aria-live="polite" class="flex flex-col gap-4">
                <template v-if="outcome === 'ready' && preview.player">
                    <h2 class="font-display text-h2">Is this you?</h2>
                    <PlayerPreviewCard :player="preview.player" />
                    <form class="flex flex-col gap-2" @submit.prevent="submitAttach">
                        <p v-if="attach.errors.tag" role="alert" class="text-sm text-danger-fg">{{ attach.errors.tag }}</p>
                        <UiButton type="submit" block :loading="attach.processing">Yes, attach this account</UiButton>
                        <p class="text-sm text-fg-secondary">Not you? Check the tag and search again.</p>
                    </form>
                </template>

                <UiAlert v-else-if="outcome === 'already_attached'" kind="info" title="You have already added this account">
                    <Link v-if="preview.accountUlid" :href="verify(preview.accountUlid).url" class="font-medium text-brand underline underline-offset-4">
                        Go to verification
                    </Link>
                </UiAlert>

                <UiAlert v-else-if="outcome === 'not_found'" kind="warning" :title="`No player has the tag ${preview.tag}`">
                    Check the tag in game and try again. A zero and the letter O look alike.
                </UiAlert>

                <UiAlert v-else-if="outcome === 'unavailable'" kind="danger" title="Clash of Clans cannot be reached right now">
                    Nothing was saved. Try again {{ waitText(preview.retryAfter) }}.
                </UiAlert>

                <UiAlert v-else-if="outcome === 'rate_limited'" kind="warning" title="Too many new tags this hour">
                    You can look up a new tag again {{ waitText(preview.retryAfter) }}. Tags you already looked up still work.
                </UiAlert>

                <template v-else-if="outcome === 'verified_elsewhere'">
                    <UiCard class="flex flex-col gap-4 p-4 sm:p-6">
                        <h2 class="font-display text-h2">
                            {{ preview.tag }} is already verified by
                            {{ preview.holderUsername ? `@${preview.holderUsername}` : 'another Clash Commons player' }}
                        </h2>
                        <PlayerPreviewCard v-if="preview.player" :player="preview.player" />
                        <p class="text-body text-fg-secondary">
                            If this is your account, verify it with your in-game API token. That moves it to you straight away.
                        </p>
                        <TokenSteps />
                        <UiAlert v-if="cocApi" kind="maintenance" title="Verification is paused">
                            Clash of Clans is unavailable right now. Nothing you entered is lost; try again once it is back.
                        </UiAlert>
                        <UiAlert v-else-if="claimMessage" :kind="claimMessage.kind" :title="claimMessage.title">{{ claimMessage.body }}</UiAlert>
                        <form ref="claimEl" class="flex flex-col gap-4" novalidate @submit.prevent="submitClaim">
                            <UiInput
                                v-model="claim.api_token"
                                label="API token"
                                autocomplete="off"
                                autocapitalize="off"
                                spellcheck="false"
                                :maxlength="64"
                                required
                                :disabled="!!cocApi"
                                :error="claim.errors.api_token ?? claim.errors.tag"
                            />
                            <UiButton type="submit" block :loading="claim.processing" :disabled="!!cocApi">Verify with token</UiButton>
                        </form>
                    </UiCard>
                </template>
            </section>
        </template>
    </div>
</template>
