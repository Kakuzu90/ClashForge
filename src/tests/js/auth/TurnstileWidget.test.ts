import TurnstileWidget from '@/Components/auth/TurnstileWidget.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

type Options = Record<string, (value?: string) => void> & { sitekey: string };

function installTurnstile() {
    const calls: { options?: Options } = {};
    window.turnstile = {
        render: vi.fn((_el: HTMLElement, options: Record<string, unknown>) => {
            calls.options = options as Options;
            return 'widget-1';
        }),
        reset: vi.fn(),
        remove: vi.fn(),
    };
    return calls;
}

afterEach(() => {
    delete window.turnstile;
});

describe('TurnstileWidget', () => {
    it('renders the widget with the site key and hands its token to the form', async () => {
        const calls = installTurnstile();
        const wrapper = mount(TurnstileWidget, { props: { siteKey: 'site-key', modelValue: null } });
        await flushPromises();

        expect(window.turnstile!.render).toHaveBeenCalledTimes(1);
        expect(calls.options!.sitekey).toBe('site-key');
        expect(calls.options!.appearance).toBe('interaction-only');

        calls.options!.callback!('token-123');
        expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual(['token-123']);

        calls.options!['expired-callback']!();
        expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([null]);
    });

    it('resets for a fresh token and removes itself on unmount', async () => {
        installTurnstile();
        const wrapper = mount(TurnstileWidget, { props: { siteKey: 'site-key', modelValue: 'old' } });
        await flushPromises();

        (wrapper.vm as unknown as { reset: () => void }).reset();
        expect(window.turnstile!.reset).toHaveBeenCalledWith('widget-1');
        expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([null]);

        const turnstile = window.turnstile!;
        wrapper.unmount();
        expect(turnstile.remove).toHaveBeenCalledWith('widget-1');
    });

    it('renders nothing without a site key, and shows the server error', () => {
        installTurnstile();
        const wrapper = mount(TurnstileWidget, { props: { siteKey: null, error: 'We could not confirm you are not a bot. Try again.' } });

        expect(window.turnstile!.render).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe('We could not confirm you are not a bot. Try again.');
    });
});
