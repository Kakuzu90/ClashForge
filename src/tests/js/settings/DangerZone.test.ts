import DangerZone from '@/Pages/Settings/DangerZone.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { remove } = vi.hoisted(() => ({ remove: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        useForm: (data: { current_password: string; confirmation: boolean }) => {
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                delete: remove,
                reset: () => {
                    form.current_password = '';
                },
            });
            return form;
        },
    };
});

beforeEach(() => remove.mockClear());

describe('Settings/DangerZone', () => {
    it('starts unchecked and submits only through the deletion form', async () => {
        const wrapper = mount(DangerZone, { props: { graceDays: 30, canRequestDeletion: true, holds: [] } });
        expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(false);
        expect(remove).not.toHaveBeenCalled();
        const password = wrapper.get('input[type="password"]');
        expect(password.attributes('autocomplete')).toBe('current-password');
        await password.setValue('current-secret');
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        expect(remove).toHaveBeenCalledWith(
            '/settings/danger-zone',
            expect.objectContaining({ onError: expect.any(Function), onFinish: expect.any(Function) }),
        );
        remove.mock.calls[0][1].onFinish();
        await wrapper.vm.$nextTick();
        expect((password.element as HTMLInputElement).value).toBe('');
        expect((wrapper.get('input[type="checkbox"]').element as HTMLInputElement).checked).toBe(true);
    });

    it('offers no deletion form without the server ability and explains the grace period', () => {
        const wrapper = mount(DangerZone, { props: { graceDays: 45, canRequestDeletion: false, holds: [] } });
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.text()).toContain('within 45 days to cancel');
        expect(wrapper.text()).toContain('unavailable while your account is suspended');
        expect(wrapper.text()).toContain('Your Clash of Clans accounts are released');
    });

    it('says why a deletion would wait, and still offers the form (specs/23 §1)', () => {
        const reason = 'You are part of an ownership dispute that is still open. Your account is deleted once it is resolved.';
        const wrapper = mount(DangerZone, { props: { graceDays: 30, canRequestDeletion: true, holds: [reason] } });

        expect(wrapper.get('[role="status"]').text()).toContain('Deletion would wait');
        expect(wrapper.text()).toContain(reason);
        expect(wrapper.find('form').exists()).toBe(true);
    });

    it('shows no hold notice when nothing holds it', () => {
        expect(mount(DangerZone, { props: { graceDays: 30, canRequestDeletion: true, holds: [] } }).text()).not.toContain('Deletion would wait');
    });

    it('does not offer the request beside a hold when the account may not request it', () => {
        const wrapper = mount(DangerZone, { props: { graceDays: 30, canRequestDeletion: false, holds: ['A dispute is still open.'] } });

        expect(wrapper.text()).toContain('A dispute is still open.');
        expect(wrapper.text()).not.toContain('You can still request it now.');
    });
});
