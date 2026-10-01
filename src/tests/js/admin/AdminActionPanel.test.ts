import AdminActionPanel from '@/Components/admin/AdminActionPanel.vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';

type Call = { method: string; url: string; data: Record<string, unknown> };
const calls: Call[] = [];

// A small stand-in for Inertia's useForm: records the request and succeeds.
vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: Record<string, unknown>) => {
        let transform: ((data: Record<string, unknown>) => Record<string, unknown>) | null = null;
        const form: Record<string, unknown> = reactive({
            ...initial,
            errors: {},
            processing: false,
            transform(fn: (data: Record<string, unknown>) => Record<string, unknown>) {
                transform = fn;
                return form;
            },
            clearErrors() {},
            reset() {
                Object.assign(form, initial);
            },
        });
        const send = (method: string) => (url: string, options: { onSuccess?: () => void }) => {
            const data = Object.fromEntries(Object.keys(initial).map((key) => [key, form[key]]));
            calls.push({ method, url, data: transform ? transform(data) : data });
            options.onSuccess?.();
        };
        form.post = send('post');
        form.delete = send('delete');
        return form;
    },
}));

enableAutoUnmount(afterEach);
beforeEach(() => {
    calls.length = 0;
    document.body.innerHTML = '';
});

const options = { reasons: [{ value: 'spam', label: 'Spam' }], maxDays: 90, publicReasonMax: 255, noteMax: 2000 };
const all = { suspend: true, ban: true, lift: true, activeType: 'suspension' };

function panel(abilities: Record<string, unknown> = all) {
    return mount(AdminActionPanel, {
        props: { ulid: '01hzzzzzzzzzzzzzzzzzzzzzzz', username: 'chief', abilities, options } as never,
        attachTo: document.body,
    });
}

const dialog = () => document.querySelector<HTMLElement>('[role="dialog"]');
const field = (label: string) =>
    [...document.querySelectorAll<HTMLLabelElement>('[role="dialog"] label')].find((l) => l.textContent?.includes(label))?.control as
        HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | undefined;

async function type(label: string, value: string) {
    const control = field(label)!;
    control.value = value;
    control.dispatchEvent(new Event(control instanceof HTMLSelectElement ? 'change' : 'input'));
    await nextTick();
}

describe('AdminActionPanel', () => {
    it('shows only the actions the server allows', () => {
        expect(
            panel()
                .findAll('button')
                .map((b) => b.text()),
        ).toEqual(['Lift the suspension', 'Suspend', 'Ban']);
        expect(
            panel({ suspend: false, ban: true, lift: false, activeType: null })
                .findAll('button')
                .map((b) => b.text()),
        ).toEqual(['Ban']);
        expect(panel({ suspend: false, ban: false, lift: false, activeType: null }).text()).toBe("You can't change this account's standing.");
    });

    it('previews what the account holder will see as the message is typed', async () => {
        const wrapper = panel();
        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Suspend')!
            .trigger('click');

        expect(dialog()?.textContent).toContain('Reason: Your message appears here.');
        await type('Message to chief', 'Spam links in comments');
        await type('Length', '3');

        const preview = document.querySelector('[aria-label="What they will see"]')?.textContent ?? '';
        expect(preview).toContain('Your account is suspended.');
        expect(preview).toContain('Reason: Spam links in comments');
        expect(preview).not.toContain('pick a length');
        expect(dialog()?.textContent).toContain('Suspend for 3 days');
    });

    it('posts a suspension with the length as a number, and a ban without one', async () => {
        const wrapper = panel();
        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Suspend')!
            .trigger('click');
        await type('Reason', 'spam');
        await type('Length', '14');
        await type('Message to chief', 'Spam links');
        await type('Internal note', 'Forty identical links.');
        document.querySelector<HTMLFormElement>('[role="dialog"] form')!.dispatchEvent(new Event('submit'));
        await nextTick();

        expect(calls[0]).toEqual({
            method: 'post',
            url: '/admin/users/01hzzzzzzzzzzzzzzzzzzzzzzz/suspension',
            data: { reason_code: 'spam', days: 14, public_reason: 'Spam links', internal_note: 'Forty identical links.' },
        });
        expect(dialog()).toBeNull();

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Ban')!
            .trigger('click');
        expect(document.querySelector('[aria-label="What they will see"]')?.textContent).toContain('can no longer sign in');
        document.querySelector<HTMLFormElement>('[role="dialog"] form')!.dispatchEvent(new Event('submit'));
        await nextTick();

        expect(calls[1]?.url).toBe('/admin/users/01hzzzzzzzzzzzzzzzzzzzzzzz/ban');
        expect(calls[1]?.data).not.toHaveProperty('days');
    });

    it('lifts with a note through DELETE', async () => {
        const wrapper = panel({ suspend: false, ban: false, lift: true, activeType: 'ban' });
        await wrapper.find('button').trigger('click');
        await type('Why lift it', 'Wrong account.');
        document.querySelector<HTMLFormElement>('[role="dialog"] form')!.dispatchEvent(new Event('submit'));
        await nextTick();

        expect(calls[0]).toEqual({ method: 'delete', url: '/admin/users/01hzzzzzzzzzzzzzzzzzzzzzzz/sanction', data: { note: 'Wrong account.' } });
    });
});
