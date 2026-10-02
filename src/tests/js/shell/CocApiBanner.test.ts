import CocApiBanner from '@/Components/shell/CocApiBanner.vue';
import Verify from '@/Pages/Accounts/Verify.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';

const page = reactive<{ url: string; props: Record<string, unknown> }>({ url: '/', props: { cocApi: null } });
const posted: string[] = [];

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    useForm: (initial: Record<string, unknown>) =>
        reactive({ ...initial, processing: false, errors: {}, post: (url: string) => posted.push(url), reset: () => undefined }),
}));

afterEach(() => {
    page.props.cocApi = null;
    posted.length = 0;
    window.sessionStorage.clear();
});

describe('CocApiBanner', () => {
    it('shows nothing while the API is available', () => {
        expect(mount(CocApiBanner).html()).not.toContain('Clash of Clans');
    });

    it('names the reason and what is paused', () => {
        page.props.cocApi = { reason: 'maintenance' };
        const text = mount(CocApiBanner).text();
        expect(text).toContain('Clash of Clans is down for maintenance');
        expect(text).toContain('verifying accounts is paused');

        page.props.cocApi = { reason: 'failures' };
        expect(mount(CocApiBanner).text()).toContain('Clash of Clans is not answering right now');
    });

    it('stays dismissed for the outage and comes back for the next one', async () => {
        page.props.cocApi = { reason: 'failures' };
        const wrapper = mount(CocApiBanner);
        await wrapper.get('button[aria-label="Dismiss"]').trigger('click');
        expect(wrapper.text()).toBe('');
        const again = mount(CocApiBanner);
        await nextTick();
        expect(again.text()).toBe('');

        page.props.cocApi = null;
        await nextTick();
        page.props.cocApi = { reason: 'maintenance' };
        await nextTick();
        expect(wrapper.text()).toContain('down for maintenance');
    });
});

it('forgets a dismissal when a page loads after the outage ended', async () => {
    window.sessionStorage.setItem('coc-api-banner-dismissed', '1');
    const wrapper = mount(CocApiBanner);
    await nextTick();

    page.props.cocApi = { reason: 'failures' };
    await nextTick();
    expect(wrapper.text()).toContain('not answering');
    expect(mount(CocApiBanner).text()).toContain('not answering');
});

describe('verification while the API is down (specs/13 §9)', () => {
    const account: App.Domain.PlayerAccounts.Data.OwnCocAccountData = {
        ulid: '01J00000000000000000000001',
        tag: '#2PQ8GRJC',
        name: 'Main',
        status: 'unverified',
        statusLabel: 'Unverified',
        townHallLevel: 16,
        featured: false,
    };

    it('pauses the token step with a message and sends nothing', async () => {
        page.props.cocApi = { reason: 'maintenance' };
        const wrapper = mount(Verify, { props: { account, result: null } });

        expect(wrapper.text()).toContain('Verification is paused');
        expect(wrapper.get('input').attributes('disabled')).toBeDefined();
        await wrapper.get('form').trigger('submit');
        expect(posted).toEqual([]);
    });

    it('lets the token through once the API is back', async () => {
        const wrapper = mount(Verify, { props: { account, result: null } });

        expect(wrapper.get('input').attributes('disabled')).toBeUndefined();
        await wrapper.get('form').trigger('submit');
        expect(posted).toEqual(['/accounts/01J00000000000000000000001/verify']);
    });
});
