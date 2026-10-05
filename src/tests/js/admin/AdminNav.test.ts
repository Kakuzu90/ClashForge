import AdminNav from '@/Components/admin/AdminNav.vue';
import { adminNav, visibleAdminNav } from '@/navigation';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const items = [
    { key: 'dashboard', label: 'Dashboard', url: '/admin' },
    { key: 'logs', label: 'Logs', url: '/admin/audit' },
];

describe('AdminNav', () => {
    it('marks only the longest matching section as current', () => {
        const current = (url: string) =>
            mount(AdminNav, { props: { items, currentUrl: url } })
                .findAll('a[aria-current="page"]')
                .map((a) => a.text());

        expect(current('/admin')).toEqual(['Dashboard']);
        expect(current('/admin/audit?actor=chief')).toEqual(['Logs']);
    });
});

describe('visibleAdminNav', () => {
    it('shows each item only with its ability', () => {
        expect(visibleAdminNav(adminNav, { accessAdmin: true }).map((item) => item.key)).toEqual(['dashboard']);
        expect(visibleAdminNav(adminNav, { accessAdmin: true, resolveDisputes: true, viewUsers: true }).map((item) => item.key)).toEqual(['dashboard', 'disputes', 'users']);
        expect(visibleAdminNav(adminNav, { accessAdmin: true, viewAuditLog: true }).map((item) => item.url)).toEqual(['/admin', '/admin/audit']);
        expect(visibleAdminNav(adminNav, { accessAdmin: true, viewUsers: true }).map((item) => item.key)).toEqual(['dashboard', 'users']);
        expect(visibleAdminNav(adminNav, { accessAdmin: true, viewPlatformStats: true }).map((item) => item.key)).toEqual(['dashboard', 'system']);
    });

    it('keeps the specs/18 §6 order: Dashboard, Users, System, Logs', () => {
        expect(
            visibleAdminNav(adminNav, { accessAdmin: true, viewUsers: true, viewPlatformStats: true, viewAuditLog: true }).map((item) => item.url),
        ).toEqual(['/admin', '/admin/users', '/admin/system', '/admin/audit']);
        expect(visibleAdminNav(adminNav, {})).toEqual([]);
    });
});
