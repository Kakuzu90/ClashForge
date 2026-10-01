<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

interface TurnstileApi {
    render(element: HTMLElement, options: Record<string, unknown>): string;
    reset(widgetId: string): void;
    remove(widgetId: string): void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

// Cloudflare Turnstile (FR-AUTH-11): stays invisible unless Cloudflare wants an interaction, and
// hands its single-use token to the form. Loaded in the browser only; without a site key (a local
// run with no keys) it renders nothing and the server decides.
const props = defineProps<{ siteKey: string | null; error?: string }>();
const token = defineModel<string | null>({ default: null });

const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
const el = ref<HTMLElement | null>(null);
// Blocked script or a Cloudflare outage: there will be no token, so say what to do about it.
const failed = ref(false);
let widgetId: string | null = null;

let loading: Promise<TurnstileApi> | null = null;
function load(): Promise<TurnstileApi> {
    if (window.turnstile) {
        return Promise.resolve(window.turnstile);
    }
    loading ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT;
        script.async = true;
        script.onload = () => (window.turnstile ? resolve(window.turnstile) : reject(new Error('Turnstile did not load')));
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile did not load'));
        };
        document.head.appendChild(script);
    });
    return loading;
}

onMounted(async () => {
    if (!props.siteKey || !el.value) {
        return;
    }
    try {
        const api = await load();
        widgetId = api.render(el.value, {
            sitekey: props.siteKey,
            appearance: 'interaction-only',
            callback: (value: string) => (token.value = value),
            'expired-callback': () => (token.value = null),
            'error-callback': () => {
                token.value = null;
                failed.value = true;
            },
        });
    } catch {
        token.value = null;
        failed.value = true;
    }
});

onBeforeUnmount(() => {
    if (widgetId && window.turnstile) {
        window.turnstile.remove(widgetId);
    }
});

/** A token works once: call after any failed submit so the next one has a fresh token. */
function reset() {
    token.value = null;
    failed.value = false;
    if (widgetId && window.turnstile) {
        window.turnstile.reset(widgetId);
    }
}

defineExpose({ reset });
</script>

<template>
    <div>
        <div ref="el" />
        <p v-if="failed" class="mt-1 text-sm text-fg-secondary" role="status">
            The bot check did not load. Turn off content blockers for this site, then reload the page.
        </p>
        <p v-if="error" class="mt-1 text-sm text-danger-fg" role="alert">{{ error }}</p>
    </div>
</template>
