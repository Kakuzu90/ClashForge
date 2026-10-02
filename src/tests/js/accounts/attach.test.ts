import { verifyMessage, waitText } from '@/Components/accounts/attachMessages';
import UiSteps from '@/Components/ui/UiSteps.vue';
import { thTone } from '@/Composables/useThTier';
import Attach from '@/Pages/Accounts/Attach.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const posted: { url: string; data: Record<string, unknown> }[] = [];

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    return {
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
        useForm: (initial: Record<string, unknown>) => {
            const form = reactive({
                ...initial,
                processing: false,
                errors: {} as Record<string, string>,
                post: (url: string, options: { onFinish?: () => void }) => {
                    posted.push({ url, data: { ...initial, ...Object.fromEntries(Object.keys(initial).map((key) => [key, (form as Record<string, unknown>)[key]])) } });
                    options.onFinish?.();
                },
                reset: (...fields: string[]) => fields.forEach((field) => ((form as Record<string, unknown>)[field] = initial[field])),
            });
            return form;
        },
    };
});

afterEach(() => {
    posted.length = 0;
});

type Page = App.Http.Data.Accounts.AttachPageData;
const player: App.Domain.PlayerAccounts.Data.CocPlayerPreviewData = {
    tag: '#2PQ8GRJC',
    name: 'Fixture Chief',
    townHallLevel: 16,
    trophies: 4800,
    expLevel: 200,
    clanName: 'Fixture Clan',
    leagueName: 'Titan League I',
    stale: false,
    fetchedAt: '2026-10-02T12:00:00+00:00',
};
const page = (overrides: Partial<Page> = {}): Page => ({ tag: '#2PQ8GRJC', preview: null, verifyResult: null, block: null, ...overrides });

describe('UiSteps', () => {
    it('marks earlier steps done and the current one with aria-current', () => {
        const wrapper = mount(UiSteps, { props: { steps: ['One', 'Two', 'Three'], current: 2 } });
        const items = wrapper.findAll('li');
        expect(items[0]!.text()).toContain('Done:');
        expect(items[1]!.attributes('aria-current')).toBe('step');
        expect(items[2]!.attributes('aria-current')).toBeUndefined();
    });
});

describe('attach messages', () => {
    it('words the wait in minutes', () => {
        expect(waitText(null)).toBe('in a minute');
        expect(waitText(45)).toBe('in a minute');
        expect(waitText(250)).toBe('in about 5 minutes');
        expect(waitText(3600)).toBe('in about an hour');
    });

    it('explains every refused token and nothing on success', () => {
        expect(verifyMessage({ outcome: 'invalid_token', accountUlid: null, superseded: false, featured: false, retryAfter: null })?.body).toContain('Tokens expire in a few minutes');
        expect(verifyMessage({ outcome: 'unavailable', accountUlid: null, superseded: false, featured: false, retryAfter: 120 })?.body).toBe(
            'Nothing was saved. Try again in about 2 minutes.',
        );
        expect(verifyMessage({ outcome: 'verified', accountUlid: 'x', superseded: false, featured: true, retryAfter: null })).toBeNull();
    });

    it('maps Town Hall levels to the tier ramp', () => {
        expect([1, 5, 8, 11, 13, 15, 17].map(thTone)).toEqual(['th-1', 'th-2', 'th-3', 'th-4', 'th-5', 'th-6', 'th-7']);
    });
});

describe('Accounts/Attach', () => {
    it('shows the confirmation card and attaches the looked-up tag', async () => {
        const wrapper = mount(Attach, { props: page({ preview: { outcome: 'ready', tag: '#2PQ8GRJC', player, accountUlid: null, holderUsername: null, retryAfter: null } }) });
        expect(wrapper.text()).toContain('Is this you?');
        expect(wrapper.text()).toContain('Fixture Chief');

        await wrapper.findAll('form')[1]!.trigger('submit');
        expect(posted).toEqual([{ url: '/accounts/attach', data: { tag: '#2PQ8GRJC' } }]);
    });

    it('attaches the tag of a card that arrived after the page mounted', async () => {
        const wrapper = mount(Attach, { props: page({ tag: null }) });
        await wrapper.setProps({ preview: { outcome: 'ready', tag: '#2PQ8GRJC', player, accountUlid: null, holderUsername: null, retryAfter: null } });

        await wrapper.findAll('form')[1]!.trigger('submit');
        expect(posted).toEqual([{ url: '/accounts/attach', data: { tag: '#2PQ8GRJC' } }]);
    });

    it('sends the tag and token from the conflict card, then clears the token', async () => {
        const wrapper = mount(Attach, {
            props: page({ preview: { outcome: 'verified_elsewhere', tag: '#2PQ8GRJC', player, accountUlid: null, holderUsername: null, retryAfter: null } }),
        });
        expect(wrapper.text()).toContain('#2PQ8GRJC is already verified by another Clash Commons player');

        const token = wrapper.findAll('input').at(-1)!;
        await token.setValue('abc123');
        await wrapper.findAll('form').at(-1)!.trigger('submit');

        expect(posted).toEqual([{ url: '/accounts/attach/verify-tag', data: { tag: '#2PQ8GRJC', api_token: 'abc123' } }]);
        expect((token.element as HTMLInputElement).value).toBe('');
    });

    it('says when the card shows saved data', () => {
        const wrapper = mount(Attach, {
            props: page({ preview: { outcome: 'ready', tag: '#2PQ8GRJC', player: { ...player, stale: true }, accountUlid: null, holderUsername: null, retryAfter: null } }),
        });
        expect(wrapper.text()).toContain('The game is not answering, so this is saved data from');
    });

    it('names a visible holder', () => {
        const wrapper = mount(Attach, {
            props: page({ preview: { outcome: 'verified_elsewhere', tag: '#2PQ8GRJC', player: null, accountUlid: null, holderUsername: 'chief', retryAfter: null } }),
        });
        expect(wrapper.text()).toContain('is already verified by @chief');
    });

    it('explains an unconfirmed email instead of showing the form', () => {
        const wrapper = mount(Attach, { props: page({ block: 'email_unverified' }) });
        expect(wrapper.text()).toContain('Confirm your email first');
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
