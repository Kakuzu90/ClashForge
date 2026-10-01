import { usePoll } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/** specs/16 §6: the bell asks for its count every minute. */
export const UNREAD_POLL_MS = 60_000;

/**
 * Refreshes the shared `unreadCount` with a partial reload while the tab is visible, and stops
 * while it is hidden (specs/16 §6): the low-cost stand-in for websockets.
 */
export function useUnreadPoll(interval = UNREAD_POLL_MS) {
    const poll = usePoll(interval, { only: ['unreadCount'] }, { autoStart: false, keepAlive: true });

    function sync() {
        if (document.visibilityState === 'visible') {
            poll.start();
        } else {
            poll.stop();
        }
    }

    onMounted(() => {
        sync();
        document.addEventListener('visibilitychange', sync);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', sync);
        poll.stop();
    });

    return poll;
}
