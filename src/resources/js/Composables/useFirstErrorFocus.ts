import { nextTick } from 'vue';

/**
 * specs/18 §8: after a failed submit, focus moves to the first invalid field so keyboard and
 * screen-reader users land on the problem. Call from an Inertia form's `onError`.
 */
export async function focusFirstError(root: HTMLElement | null): Promise<void> {
    await nextTick();
    root?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
}
