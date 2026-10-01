import SettingsEmailSection from '@/Components/settings/SettingsEmailSection.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const sent: { method: string; url: string; data: Record<string, unknown> }[] = [];
let failWith: Record<string, string> | null = null;

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        useForm: (initial: Record<string, unknown>) => {
            const visit =
                (method: string) =>
                (url: string, options: { onError?: () => void; onSuccess?: () => void } = {}) => {
                    const {
                        errors: _e,
                        processing: _p,
                        recentlySuccessful: _s,
                        put: _put,
                        post: _post,
                        delete: _d,
                        reset: _r,
                        ...data
                    } = form as Record<string, unknown>;
                    sent.push({ method, url, data });
                    if (failWith) {
                        form.errors = failWith;
                        options.onError?.();
                    } else {
                        options.onSuccess?.();
                    }
                };
            const form = reactive({
                ...initial,
                errors: {} as Record<string, string>,
                processing: false,
                recentlySuccessful: false,
                put: visit('put'),
                post: visit('post'),
                delete: visit('delete'),
                reset: vi.fn(),
            });
            return form;
        },
    };
});

const base = { email: 'c***@example.com', pendingEmail: null, linkMinutes: 60 };

afterEach(() => {
    sent.length = 0;
    failWith = null;
    document.body.innerHTML = '';
});

describe('SettingsEmailSection', () => {
    it('shows the masked address and only the form when nothing is pending', () => {
        const wrapper = mount(SettingsEmailSection, { props: base });

        expect(wrapper.text()).toContain('c***@example.com');
        expect(wrapper.find('[data-test="pending-email"]').exists()).toBe(false);
        expect(wrapper.get('label').text()).toContain('New email');
    });

    it('puts the new address with the current password to the email route', async () => {
        const wrapper = mount(SettingsEmailSection, { props: base });

        await wrapper.get('input[type="email"]').setValue('new@example.com');
        await wrapper.get('input[type="password"]').setValue('secret-pass');
        await wrapper.get('form').trigger('submit');

        expect(sent).toEqual([{ method: 'put', url: '/settings/security/email', data: { email: 'new@example.com', current_password: 'secret-pass' } }]);
    });



    it('offers resend and cancel for a pending change', async () => {
        const wrapper = mount(SettingsEmailSection, { props: { ...base, pendingEmail: 'n***@example.com' } });

        expect(wrapper.get('[data-test="pending-email"]').text()).toContain('n***@example.com');
        expect(wrapper.get('label').text()).toContain('Use a different new email');

        const [again, cancel] = wrapper.get('[data-test="pending-email"]').findAll('button');
        await again.trigger('click');
        await cancel.trigger('click');

        expect(sent.map(({ method, url }) => `${method} ${url}`)).toEqual([
            'post /settings/security/email/resend',
            'delete /settings/security/email',
        ]);
    });

    it('moves focus to the field after a failed submit', async () => {
        failWith = { email: 'Enter a valid email address.' };
        const wrapper = mount(SettingsEmailSection, { props: base, attachTo: document.body });

        await wrapper.get('form').trigger('submit');
        await new Promise((resolve) => setTimeout(resolve));

        expect(wrapper.text()).toContain('Enter a valid email address.');
        expect(document.activeElement).toBe(wrapper.get('input[type="email"]').element);
    });
});
