import NotificationItem from '@/Components/notifications/NotificationItem.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href', 'method', 'as', 'preserveScroll'],
        template: '<button :data-href="href" :data-method="method" :data-preserve-scroll="String(preserveScroll)"><slot /></button>',
    },
}));

type Item = App.Domain.Notifications.Data.NotificationItemData;

const item = (overrides: Partial<Item> = {}): Item => ({
    id: '0199a8f0-0000-7000-8000-000000000001',
    category: 'security',
    title: 'Your password was changed',
    body: 'Every other device was signed out.',
    hasTarget: true,
    read: false,
    createdAt: '2026-10-01T12:00:00+00:00',
    ...overrides,
});

describe('NotificationItem', () => {
    it('marks an unread row with the gold border and a New label, and posts to mark it read', () => {
        const wrapper = mount(NotificationItem, { props: { notification: item() } });
        const button = wrapper.get('button');

        expect(button.classes()).toContain('border-l-brand');
        expect(button.text()).toContain('New');
        expect(button.attributes('data-href')).toBe('/notifications/0199a8f0-0000-7000-8000-000000000001/read');
        expect(button.attributes('data-method')).toBe('post');
        expect(wrapper.get('time').attributes('datetime')).toBe('2026-10-01T12:00:00+00:00');
    });

    it('keeps the scroll position when there is nowhere to go', () => {
        const wrapper = mount(NotificationItem, { props: { notification: item({ hasTarget: false }) } });

        expect(wrapper.get('button').attributes('data-preserve-scroll')).toBe('true');
    });

    it('renders a read row with nowhere to go as plain text', () => {
        const wrapper = mount(NotificationItem, { props: { notification: item({ read: true, hasTarget: false }) } });

        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('New');
    });

    it('shows a staff-written reason as text', () => {
        const wrapper = mount(NotificationItem, { props: { notification: item({ body: 'Reason: <img src=x onerror=alert(1)>' }) } });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
    });
});
