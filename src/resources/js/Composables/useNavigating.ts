import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * True while a full GET visit to a matching URL is in flight, so a page can show its skeleton
 * instead of stale content (specs/18 §6 loading states). Prefetches and partial reloads are ignored.
 */
export function useNavigating(matches: (url: URL) => boolean) {
    const navigating = ref(false);
    let stop: (() => void)[] = [];

    onMounted(() => {
        stop = [
            router.on('start', (event) => {
                const visit = event.detail.visit;
                if (visit.method === 'get' && !visit.prefetch && visit.only.length === 0 && matches(visit.url)) {
                    navigating.value = true;
                }
            }),
            router.on('finish', () => {
                navigating.value = false;
            }),
        ];
    });

    onBeforeUnmount(() => stop.forEach((off) => off()));

    return navigating;
}
