import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

export interface VisitError {
    /** From the X-Request-Id response header, for support correlation (specs/18 §6 admin error state). */
    requestId: string | null;
    /** A rate limit (429): the page asks to wait instead of reporting a failure. */
    throttled: boolean;
}

/**
 * Turns a failed visit (a 5xx, a 429, or no response at all) into an inline error instead of
 * Inertia's error modal, so a page can show what failed with the request id. Cleared by the next
 * visit.
 */
export function useVisitError() {
    const error = ref<VisitError | null>(null);
    let stop: (() => void)[] = [];

    onMounted(() => {
        stop = [
            router.on('start', () => {
                error.value = null;
            }),
            router.on('invalid', (event) => {
                const response = event.detail.response;
                if (response.status >= 500 || response.status === 429) {
                    event.preventDefault();
                    const header = response.headers?.['x-request-id'];
                    error.value = { requestId: typeof header === 'string' ? header : null, throttled: response.status === 429 };
                }
            }),
            router.on('exception', (event) => {
                event.preventDefault();
                error.value = { requestId: null, throttled: false };
            }),
        ];
    });

    onBeforeUnmount(() => stop.forEach((off) => off()));

    return error;
}
