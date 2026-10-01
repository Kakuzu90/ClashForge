import Unsubscribe from '@/Pages/Notifications/Unsubscribe.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const post = vi.fn();
vi.mock('@/Composables/useVisitError', () => ({ useVisitError: () => ref(null) }));
vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: (...args: unknown[]) => post(...args) },
}));

beforeEach(() => {
    post.mockReset();
});

describe('Notifications/Unsubscribe', () => {
    it('changes nothing until the button and prevents repeated clicks while submitting', async () => {
        post.mockImplementation((_url, _data, options) => options.onStart());
        const wrapper = mount(Unsubscribe, {
            props: { outcome: 'pending', message: 'Security emails stay on.', confirmUrl: 'https://example.test/signed' },
        });
        expect(post).not.toHaveBeenCalled();
        await wrapper.get('button').trigger('click');
        expect(post).toHaveBeenCalledWith('https://example.test/signed', {}, expect.any(Object));
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
        await wrapper.get('button').trigger('click');
        expect(post).toHaveBeenCalledTimes(1);
    });

    it('shows success and invalid states without a submit button', () => {
        for (const outcome of ['unsubscribed', 'invalid']) {
            const wrapper = mount(Unsubscribe, { props: { outcome, message: 'The result.', confirmUrl: null } });
            expect(wrapper.find('button').exists()).toBe(false);
            expect(wrapper.get('h1').text()).toBe(outcome === 'unsubscribed' ? 'Emails turned off' : 'Link not working');
            expect(wrapper.get('a').attributes('href')).toBe('/settings/notifications');
        }
    });

    it('makes a failed submission retryable', async () => {
        post.mockImplementation((_url, _data, options) => {
            options.onStart();
            options.onError();
            options.onFinish();
        });
        const wrapper = mount(Unsubscribe, { props: { outcome: 'pending', message: '', confirmUrl: 'https://example.test/signed' } });
        await wrapper.get('button').trigger('click');
        expect(wrapper.text()).toContain('We could not save that change. Try again.');
        expect(wrapper.get('button').attributes('disabled')).toBeUndefined();
    });
});
