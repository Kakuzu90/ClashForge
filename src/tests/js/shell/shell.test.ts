import BottomNav from '@/Components/shell/BottomNav.vue';
import SideNav from '@/Components/shell/SideNav.vue';
import SiteFooter from '@/Components/shell/SiteFooter.vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

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

describe('SideNav', () => {
    beforeEach(() => localStorage.clear());

    it('collapses, keeps labels for screen readers and remembers the choice', async () => {
        const wrapper = mount(SideNav, { props: { items, currentUrl: '/' } });
        const toggle = wrapper.get('button');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('li span.sr-only').text()).toBe('Home');
        expect(localStorage.getItem('shell.sidebar.collapsed')).toBe('1');
    });

    it('starts collapsed when the browser remembered it', async () => {
        localStorage.setItem('shell.sidebar.collapsed', '1');
        const wrapper = mount(SideNav, { props: { items, currentUrl: '/' } });
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
    });
});

describe('audit-003 regressions', () => {
    beforeEach(() => localStorage.clear());

    it('only animates the sidebar width after a user toggle', async () => {
        localStorage.setItem('shell.sidebar.collapsed', '1');
        const wrapper = mount(SideNav, { props: { items, currentUrl: '/' } });
        await wrapper.vm.$nextTick();
        expect(wrapper.get('aside').classes()).not.toContain('transition-[width]');
        await wrapper.get('button').trigger('click');
        expect(wrapper.get('aside').classes()).toContain('transition-[width]');
    });

    it('keeps one toggle label and lets aria-expanded carry the state', async () => {
        const wrapper = mount(SideNav, { props: { items, currentUrl: '/' } });
        const toggle = wrapper.get('button');
        expect(toggle.text()).toBe('Sidebar');
        await toggle.trigger('click');
        expect(toggle.text()).toBe('Sidebar');
    });

    it('marks the active tab with a shape, not only colour', () => {
        const wrapper = mount(BottomNav, { props: { items, currentUrl: '/' } });
        expect(wrapper.findAll('a')[0].find('span.bg-brand\\/20').exists()).toBe(true);
        expect(wrapper.findAll('a')[1].find('span.bg-brand\\/20').exists()).toBe(false);
    });
});
