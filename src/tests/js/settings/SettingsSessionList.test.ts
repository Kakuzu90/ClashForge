import SettingsSessionList from '@/Components/settings/SettingsSessionList.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const destroy = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { delete: (...args: unknown[]) => destroy(...args) },
}));

type Session = App.Domain.Auth.Data.SessionData;

const session = (overrides: Partial<Session>): Session => ({
    key: 'a'.repeat(32),
    deviceLabel: 'Firefox on Windows',
    country: 'Germany',
    lastActiveAt: '2026-10-01T10:00:00+00:00',
    signedInAt: '2026-09-30T10:00:00+00:00',
    isCurrent: false,
    ...overrides,
});

beforeEach(() => destroy.mockClear());

const confirmButton = () => [...document.querySelectorAll<HTMLButtonElement>('[role="dialog"] button')].find((b) => b.textContent?.trim() === 'Sign out');

describe('SettingsSessionList', () => {
    it('shows only this device with no sign-out controls', () => {
        const wrapper = mount(SettingsSessionList, { props: { sessions: [session({ isCurrent: true, deviceLabel: 'Safari on iOS' })] } });

        expect(wrapper.text()).toContain('This device');
        expect(wrapper.text()).toContain('Active now');
        expect(wrapper.text()).toContain('You are only signed in on this device.');
        expect(wrapper.findAll('button')).toHaveLength(0);
    });

    it('asks before signing out one device, then deletes that key', async () => {
        const other = session({ key: 'b'.repeat(32) });
        const wrapper = mount(SettingsSessionList, { props: { sessions: [session({ isCurrent: true }), other] }, attachTo: document.body });

        await wrapper.findAll('button').find((b) => b.text() === 'Sign out')!.trigger('click');
        await nextTick();
        expect(document.body.textContent).toContain('Sign out Firefox on Windows?');
        expect(destroy).not.toHaveBeenCalled();

        confirmButton()!.click();
        expect(destroy).toHaveBeenCalledWith(`/settings/security/sessions/${'b'.repeat(32)}`, expect.any(Object));
        wrapper.unmount();
    });

    it('asks before signing out every other device', async () => {
        const wrapper = mount(SettingsSessionList, {
            props: { sessions: [session({ isCurrent: true }), session({ key: 'b'.repeat(32) }), session({ key: 'c'.repeat(32) })] },
            attachTo: document.body,
        });

        await wrapper.findAll('button').find((b) => b.text() === 'Sign out every other device')!.trigger('click');
        await nextTick();
        expect(document.body.textContent).toContain('All 2 other devices will need your password');

        confirmButton()!.click();
        expect(destroy).toHaveBeenCalledWith('/settings/security/sessions', expect.any(Object));
        wrapper.unmount();
    });
});
