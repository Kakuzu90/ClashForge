import BottomNav from '@/Components/shell/BottomNav.vue';
import HeaderNav from '@/Components/shell/HeaderNav.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } }));

const items = [
    { key: 'home', label: 'Home', icon: 'home' as const, url: '/' },
    { key: 'bases', label: 'Bases', icon: 'bases' as const, url: '/bases' },
];

describe('SiteFooter', () => {
    it('shows the fan-content disclaimer verbatim with the policy link', () => {
        const wrapper = mount(SiteFooter);
        expect(wrapper.text()).toContain(
            "This material is unofficial and is not endorsed by Supercell. For more information see Supercell's Fan Content Policy.",
        );
        expect(wrapper.get('a').attributes('href')).toBe('https://supercell.com/en/fan-content-policy/');
    });
});

describe('BottomNav', () => {
    it('marks the current section', () => {
        const wrapper = mount(BottomNav, { props: { items, currentUrl: '/bases/abc' } });
        const links = wrapper.findAll('a');
        expect(links[0].attributes('aria-current')).toBeUndefined();
        expect(links[1].attributes('aria-current')).toBe('page');
    });
});

describe('audit-003 regressions', () => {
    it('marks the active tab with a shape, not only colour', () => {
        const wrapper = mount(BottomNav, { props: { items, currentUrl: '/' } });
        expect(wrapper.findAll('a')[0].find('span.bg-brand\\/20').exists()).toBe(true);
        expect(wrapper.findAll('a')[1].find('span.bg-brand\\/20').exists()).toBe(false);
    });
});

describe('HeaderNav', () => {
    it('shows text links only, the current one gold with a bottom bar', () => {
        const wrapper = mount(HeaderNav, { props: { items, currentUrl: '/' } });
        const [home, bases] = wrapper.findAll('a');

        expect(wrapper.find('svg').exists()).toBe(false);
        expect(home.text()).toBe('Home');
        expect(home.attributes('aria-current')).toBe('page');
        expect(home.classes()).toContain('text-brand');
        expect(home.find('span.bg-brand').exists()).toBe(true);
        expect(bases.attributes('aria-current')).toBeUndefined();
        expect(bases.find('span.bg-brand').exists()).toBe(false);
    });
});
