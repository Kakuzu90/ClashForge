import { useToast } from '@/Composables/useToast';
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';

type Flash = { success?: string | null; error?: string | null } | null | undefined;

// The flash object last turned into toasts, shared by every toaster, so a layout swap (one
// toaster unmounting, another mounting on the same page) cannot show a message twice.
let shown: Flash = null;

/**
 * Shows the server's `flash.success` / `flash.error` (shared props) as toasts. Every visit brings
 * a new flash object, so the same message after a second save shows again; a partial reload keeps
 * the old object and shows nothing.
 */
export function useFlashToasts(): void {
    if (import.meta.env.SSR) {
        return;
    }

    const page = usePage();
    const { push } = useToast();

    watch(
        () => page.props?.flash as Flash,
        (flash) => {
            if (!flash || flash === shown) {
                return;
            }
            shown = flash;
            if (flash.success) {
                push(flash.success, { kind: 'success' });
            }
            if (flash.error) {
                push(flash.error, { kind: 'danger' });
            }
        },
        { immediate: true },
    );
}
