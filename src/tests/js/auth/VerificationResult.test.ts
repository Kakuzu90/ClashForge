import VerificationResult from '@/Pages/Auth/VerificationResult.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: (...args: unknown[]) => post(...args) },
}));

const base = { message: '', signedIn: false, username: null, confirmUrl: null };

describe('Auth/VerificationResult', () => {
    it('names the account and confirms only on the button', async () => {
        const wrapper = mount(VerificationResult, {
            props: { ...base, outcome: 'pending', username: 'chief', confirmUrl: 'https://example.test/email/verify/x/y?signature=z' },
        });

        expect(wrapper.text()).toContain('the account chief');
        expect(post).not.toHaveBeenCalled();

        await wrapper.get('button').trigger('click');
        expect(post).toHaveBeenCalledWith('https://example.test/email/verify/x/y?signature=z', {}, expect.any(Object));
    });

    it('offers sign in after confirming without a session, and continue with one', () => {
        expect(mount(VerificationResult, { props: { ...base, outcome: 'verified' } }).get('a').attributes('href')).toBe('/login');
        expect(mount(VerificationResult, { props: { ...base, outcome: 'verified', signedIn: true } }).get('a').attributes('href')).toBe('/');
    });

    it('offers a new link for a dead one', () => {
        const wrapper = mount(VerificationResult, { props: { ...base, outcome: 'invalid', message: 'This link has already been used or has expired. Ask for a new one.' } });

        expect(wrapper.get('h1').text()).toBe('Link not working');
        expect(wrapper.find('a[href="/email/verify"]').exists()).toBe(true);
    });
});
