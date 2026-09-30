import { focusFirstError } from '@/Composables/useFirstErrorFocus';
import { describe, expect, it } from 'vitest';

describe('focusFirstError', () => {
    it('focuses the first invalid field in the form', async () => {
        document.body.innerHTML = `
            <form>
                <input id="ok" />
                <input id="first" aria-invalid="true" />
                <input id="second" aria-invalid="true" />
            </form>`;

        await focusFirstError(document.querySelector('form'));

        expect(document.activeElement?.id).toBe('first');
    });

    it('does nothing without a form or an invalid field', async () => {
        document.body.innerHTML = '<form><input id="ok" /></form>';

        await focusFirstError(null);
        await focusFirstError(document.querySelector('form'));

        expect(document.activeElement).toBe(document.body);
    });
});
