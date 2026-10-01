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
        const wrapper = mount(DangerZone, { props: { graceDays: 30, canRequestDeletion: true } });
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
        const wrapper = mount(DangerZone, { props: { graceDays: 45, canRequestDeletion: false } });
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.text()).toContain('within 45 days to cancel');
        expect(wrapper.text()).toContain('unavailable while your account is suspended');
    });
});
