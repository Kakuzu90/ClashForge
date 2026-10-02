import Notifications from '@/Pages/Settings/Notifications.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const submitted: Record<string, unknown>[] = [];
let failure: Record<string, string> | null = null;
vi.mock('@/Composables/useVisitError', () => ({ useVisitError: () => ref(null) }));
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        useForm: (initial: { email_enabled: boolean; email_categories: Record<string, boolean> }) => {
            const form = reactive({
                ...initial,
                processing: false,
                recentlySuccessful: false,
                errors: {} as Record<string, string>,
                patch: (url: string, options: { onError?: () => void }) => {
                    submitted.push({ url, email_enabled: form.email_enabled, email_categories: { ...(form.email_categories as object) } });
                    if (failure) {
                        form.errors = failure;
                        options.onError?.();
                    } else {
                        form.recentlySuccessful = true;
                    }
                },
            });
            return form;
        },
    };
});

const settings = {
    emailEnabled: true,
    canUpdate: true,
    categories: [
        { key: 'security', label: 'Security', enabled: true, locked: true, hint: null },
        { key: 'ownership', label: 'Accounts', enabled: true, locked: false, hint: 'Takeover alerts are always sent.' },
        { key: 'bases', label: 'Bases', enabled: true, locked: false, hint: null },
        { key: 'social', label: 'Social', enabled: false, locked: false, hint: null },
    ],
};

afterEach(() => {
    submitted.length = 0;
    failure = null;
    document.body.innerHTML = '';
});

describe('Settings/Notifications', () => {
    it('shows Security as always on and submits editable email controls only', async () => {
        const wrapper = mount(Notifications, { props: { settings } });
        expect(wrapper.text()).toContain('Always on');
        await wrapper.get('form').trigger('submit');
        expect(submitted).toEqual([{ url: '/settings/notifications', email_enabled: true, email_categories: { ownership: true, bases: true, social: false } }]);
    });

    it('disables categories without losing their choices when global email is off', async () => {
        const wrapper = mount(Notifications, { props: { settings } });
        const switches = wrapper.findAll('input[role="switch"]');
        await switches[0].setValue(false);
        expect(switches[1].attributes('disabled')).toBeDefined();
        await wrapper.get('form').trigger('submit');
        expect(submitted[0]).toMatchObject({ email_enabled: false, email_categories: { ownership: true, bases: true, social: false } });
    });

    it('focuses the invalid category after a refused update', async () => {
        failure = { 'email_categories.bases': 'Choose on or off.' };
        const wrapper = mount(Notifications, { props: { settings }, attachTo: document.body });
        await wrapper.get('form').trigger('submit');
        await new Promise((resolve) => setTimeout(resolve));
        expect(wrapper.text()).toContain('Choose on or off.');
        expect(document.activeElement).toBe(wrapper.findAll('input[role="switch"]')[2].element);
    });

    it('describes the Accounts toggle with its hint', () => {
        const wrapper = mount(Notifications, { props: { settings } });
        const accounts = wrapper.findAll('input[role="switch"]')[1];
        const hint = wrapper.get(`#${accounts.attributes('aria-describedby')}`);
        expect(hint.text()).toBe('Takeover alerts are always sent.');
        expect(wrapper.findAll('input[role="switch"]')[2].attributes('aria-describedby')).toBeUndefined();
    });

    it('honors the server update ability', () => {
        const wrapper = mount(Notifications, { props: { settings: { ...settings, canUpdate: false } } });
        expect(wrapper.find('button[type="submit"]').exists()).toBe(false);
        expect(wrapper.findAll('input[role="switch"]').every((input) => input.attributes('disabled') !== undefined)).toBe(true);
    });
});
