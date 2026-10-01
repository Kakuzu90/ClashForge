import NotificationBell from '@/Components/shell/NotificationBell.vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const poll = { start: vi.fn(), stop: vi.fn() };
const usePoll = vi.fn(() => poll);
let unreadCount: number | null = 0;

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePoll: (...args: unknown[]) => usePoll(...(args as [])),
    usePage: () => ({ url: '/', props: { auth: { user: { username: 'chief' }, can: {} }, flash: {}, unreadCount } }),
}));

function setVisibility(state: 'visible' | 'hidden') {
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state });
    document.dispatchEvent(new Event('visibilitychange'));
}

enableAutoUnmount(afterEach);

beforeEach(() => {
    poll.start.mockClear();
    poll.stop.mockClear();
    usePoll.mockClear();
    setVisibility('visible');
});

afterEach(() => {
    unreadCount = 0;
});

describe('NotificationBell', () => {
    it('links to the notification centre and hides the badge at zero', () => {
        const wrapper = mount(NotificationBell);
        const link = wrapper.get('a');

        expect(link.attributes('href')).toBe('/notifications');
        expect(link.attributes('aria-label')).toBe('Notifications');
        expect(link.find('span').exists()).toBe(false);
    });

    it('shows the count, capped at 99+, and says it', () => {
        unreadCount = 120;
        const wrapper = mount(NotificationBell);

        expect(wrapper.get('a span').text()).toBe('99+');
        expect(wrapper.get('a').attributes('aria-label')).toBe('Notifications, 99+ unread');
        expect(wrapper.get('[aria-live="polite"]').text()).toBe('99+ unread notifications');
    });

    it('polls only the unread count, every minute, while the tab is visible', () => {
        const wrapper = mount(NotificationBell);

        expect(usePoll).toHaveBeenCalledWith(60_000, { only: ['unreadCount'] }, { autoStart: false, keepAlive: true });
        expect(poll.start).toHaveBeenCalledTimes(1);

        setVisibility('hidden');
        expect(poll.stop).toHaveBeenCalledTimes(1);

        setVisibility('visible');
        expect(poll.start).toHaveBeenCalledTimes(2);

        wrapper.unmount();
        expect(poll.stop).toHaveBeenCalledTimes(2);
        setVisibility('hidden');
        expect(poll.stop).toHaveBeenCalledTimes(2);
    });
});
