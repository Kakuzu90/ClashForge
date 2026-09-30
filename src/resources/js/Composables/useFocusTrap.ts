import { nextTick, onBeforeUnmount, onMounted, watch, type Ref } from 'vue';

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Keeps Tab focus inside `container` while `active` is true, closes on Escape, and restores focus to
 * the previously focused element on deactivation (specs/18 §8).
 */
export function useFocusTrap(container: Ref<HTMLElement | null>, active: Ref<boolean>, onEscape: () => void) {
    let previous: HTMLElement | null = null;

    const focusables = (): HTMLElement[] =>
        container.value ? Array.from(container.value.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((el) => !el.hasAttribute('inert')) : [];

    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            event.preventDefault();
            onEscape();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const items = focusables();
        if (items.length === 0) {
            event.preventDefault();
            container.value?.focus();
            return;
        }

        const first = items[0];
        const last = items[items.length - 1];
        const current = document.activeElement;

        if (event.shiftKey && (current === first || current === container.value)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && current === last) {
            event.preventDefault();
            first.focus();
        }
    }

    async function activate() {
        previous = document.activeElement as HTMLElement | null;
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        (focusables()[0] ?? container.value)?.focus();
    }

    function deactivate() {
        document.removeEventListener('keydown', onKeydown);
        previous?.focus();
        previous = null;
    }

    watch(active, (isActive) => (isActive ? activate() : deactivate()));
    // Mounted already open: trap immediately (onMounted is client-only, so SSR never touches document).
    onMounted(() => {
        if (active.value) {
            activate();
        }
    });
    onBeforeUnmount(() => {
        if (active.value) {
            deactivate();
        }
    });
}
