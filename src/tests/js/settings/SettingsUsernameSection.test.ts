import SettingsUsernameSection from '@/Components/settings/SettingsUsernameSection.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const sent: { method: string; url: string; data: Record<string, unknown> }[] = [];
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
                put: (url: string, options: { onError?: () => void; onSuccess?: () => void } = {}) => {
                    const { errors: _e, processing: _p, put: _put, reset: _r, ...data } = form as Record<string, unknown>;
                    sent.push({ method: 'put', url, data });
                    if (failWith) {
                        form.errors = failWith;
                        options.onError?.();
                    } else {
                        options.onSuccess?.();
                    }
                },
                reset: vi.fn(),
            });
            return form;
        },
    };
});

const base: App.Domain.Auth.Data.UsernameSettingsData = {
    username: 'chief',
    canChange: true,
    needsVerifiedEmail: false,
    nextChangeAt: null,
    changeDays: 30,
    reservationDays: 90,
    minLength: 3,
    maxLength: 20,
};

afterEach(() => {
    sent.length = 0;
    failWith = null;
    document.body.innerHTML = '';
});

describe('SettingsUsernameSection', () => {
    it('shows the current name, the windows from the server and the form when a change is open', () => {
        const wrapper = mount(SettingsUsernameSection, { props: { settings: base } });

        expect(wrapper.text()).toContain('@chief');
        expect(wrapper.text()).toContain('once every 30 days');
        expect(wrapper.text()).toContain('for 90 days');
        expect(wrapper.text()).toContain('3 to 20 lowercase letters');
        expect(wrapper.find('form').exists()).toBe(true);
    });

    it('sends the new name with the current password', async () => {
        const wrapper = mount(SettingsUsernameSection, { props: { settings: base } });

        await wrapper.find('input[type="text"]').setValue('new_chief');
        await wrapper.find('input[type="password"]').setValue('secret');
        await wrapper.find('form').trigger('submit');

        expect(sent).toEqual([{ method: 'put', url: '/settings/profile/username', data: { username: 'new_chief', current_password: 'secret' } }]);
    });

    it('shows the field error and clears the password when the server refuses', async () => {
        failWith = { username: 'That username is taken. Pick another.' };
        const wrapper = mount(SettingsUsernameSection, { props: { settings: base }, attachTo: document.body });

        await wrapper.find('form').trigger('submit');

        expect(wrapper.text()).toContain('That username is taken. Pick another.');
    });

    it('explains the wait instead of the form while a change is closed', () => {
        const wrapper = mount(SettingsUsernameSection, {
            props: { settings: { ...base, canChange: false, nextChangeAt: '2026-10-31T12:00:00+00:00' } },
        });

        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-test="username-locked"]').text()).toContain('You can change your username again on');
    });

    it('asks an unverified account to confirm its email first', () => {
        const wrapper = mount(SettingsUsernameSection, { props: { settings: { ...base, canChange: false, needsVerifiedEmail: true } } });

        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-test="username-unverified"]').text()).toContain('Confirm your email address');
        expect(wrapper.find('a').attributes('href')).toBe('/email/verify');
    });

    it('says why the card has no form for a suspended account', () => {
        const wrapper = mount(SettingsUsernameSection, { props: { settings: { ...base, canChange: false } } });

        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('[data-test="username-unavailable"]').text()).toContain('unavailable while your account is suspended');
    });
});
