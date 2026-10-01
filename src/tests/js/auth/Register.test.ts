import Register from '@/Pages/Auth/Register.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const posted: { url?: string; data?: Record<string, unknown> } = {};
let failWith: Record<string, string> | null = null;

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        useForm: (initial: Record<string, unknown>) => {
            const form = reactive({
                ...initial,
                errors: {} as Record<string, string>,
                processing: false,
                post(url: string, options: { onError?: () => void; onFinish?: () => void } = {}) {
                    posted.url = url;
                    const { errors: _e, processing: _p, post: _post, reset: _r, ...data } = form as Record<string, unknown>;
                    posted.data = data;
                    if (failWith) {
                        form.errors = failWith;
                        options.onError?.();
                    }
                    options.onFinish?.();
                },
                reset: vi.fn(),
            });
            return form;
        },
    };
});

const props = { usernameMin: 3, usernameMax: 20, passwordMin: 10, turnstileSiteKey: 'site-key', formStarted: 'encrypted-start' };

afterEach(() => {
    failWith = null;
    delete window.turnstile;
    document.body.innerHTML = '';
});

describe('Auth/Register', () => {
    it('posts the fields with the form token and an empty bot trap', async () => {
        const wrapper = mount(Register, { props });

        await wrapper.get('input[type="email"]').setValue('newcomer@example.com');
        await wrapper.get('form').trigger('submit');

        expect(posted.url).toBe('/register');
        expect(posted.data).toMatchObject({ email: 'newcomer@example.com', started: 'encrypted-start', website: '', turnstile_token: null });
    });

    it('focuses the first invalid field and resets Turnstile after a failed submit', async () => {
        window.turnstile = { render: vi.fn(() => 'widget-1'), reset: vi.fn(), remove: vi.fn() };
        const wrapper = mount(Register, { props, attachTo: document.body });
        await flushPromises();
        failWith = { username: 'That username is taken. Pick another.' };

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(window.turnstile.reset).toHaveBeenCalledWith('widget-1');
        expect(document.activeElement?.getAttribute('autocomplete')).toBe('username');
        expect(wrapper.text()).toContain('That username is taken. Pick another.');
        wrapper.unmount();
    });

    it('hides the bot trap from people and assistive tech, out of the tab order', () => {
        const wrapper = mount(Register, { props });
        const trap = wrapper.get('#register-website');

        expect(trap.attributes('tabindex')).toBe('-1');
        expect(trap.element.closest('[aria-hidden="true"]')).not.toBeNull();
        expect(trap.element.closest('.sr-only')).not.toBeNull();
    });

    it('explains the username and password rules', () => {
        const text = mount(Register, { props }).text();

        expect(text).toContain('3 to 20 lowercase letters, numbers or underscores');
        expect(text).toContain('At least 10 characters.');
    });
});
