<script setup lang="ts">
import TokenSteps from '@/Components/accounts/TokenSteps.vue';
import { verifyMessage } from '@/Components/accounts/attachMessages';
import UiAlert from '@/Components/ui/UiAlert.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiInput from '@/Components/ui/UiInput.vue';
import UiSteps from '@/Components/ui/UiSteps.vue';
import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { usePageProps } from '@/Composables/usePageProps';
import AppLayout from '@/Layouts/AppLayout.vue';
import { store } from '@/routes/accounts/verify';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

type Props = App.Http.Data.Accounts.VerifyPageData;
const props = defineProps<{ account: Props['account']; result: Props['result'] }>();

const form = useForm({ api_token: '' });
const formEl = ref<HTMLFormElement | null>(null);
const message = computed(() => verifyMessage(props.result));
// specs/13 §9: verification is paused while the API is unavailable; the field keeps its value.
const { cocApi } = usePageProps();

function submit() {
    if (cocApi.value) return;
    // The token is good once and expires in minutes: never keep it in the field after a submit.
    form.post(store(props.account.ulid).url, {
        preserveScroll: true,
        onError: () => focusFirstError(formEl.value),
        onFinish: () => form.reset('api_token'),
    });
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-xl flex-col gap-6 py-6 md:py-10">
        <header class="flex flex-col gap-4">
            <h1 class="font-display text-h1">Prove {{ account.name }} is yours</h1>
            <!-- A holder answering a dispute with a token (specs/13 §5 3a) is not in the attach flow. -->
            <template v-if="account.status === 'disputed'">
                <p class="text-body text-fg-secondary">
                    Someone claims <span class="font-mono">{{ account.tag }}</span>. A new token from the game proves it is yours and ends the
                    review at once.
                </p>
            </template>
            <template v-else>
                <UiSteps :steps="['Find your account', 'Prove it is yours', 'Done']" :current="2" label="Attach steps" />
                <p class="text-body text-fg-secondary">
                    <span class="font-mono">{{ account.tag }}</span> is attached but not verified yet. Only someone who can open the account in game
                    can get its API token.
                </p>
            </template>
        </header>

        <UiCard class="flex flex-col gap-5 p-4 sm:p-6">
            <TokenSteps />
            <UiAlert v-if="cocApi" kind="maintenance" title="Verification is paused">
                Clash of Clans is unavailable right now. Nothing you entered is lost; try again once it is back.
            </UiAlert>
            <UiAlert v-else-if="message" :kind="message.kind" :title="message.title">{{ message.body }}</UiAlert>
            <form ref="formEl" class="flex flex-col gap-4" novalidate @submit.prevent="submit">
                <UiInput
                    v-model="form.api_token"
                    label="API token"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    :maxlength="64"
                    required
                    autofocus
                    :disabled="!!cocApi"
                    :error="form.errors.api_token"
                />
                <UiButton type="submit" block :loading="form.processing" :disabled="!!cocApi">Verify</UiButton>
            </form>
        </UiCard>
    </div>
</template>
