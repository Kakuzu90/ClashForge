import AdminAuditTrailList from '@/Components/admin/AdminAuditTrailList.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

type Entry = App.Http.Data.Admin.AuditTrailEntryData;

function entry(overrides: Partial<Entry>): Entry {
    return {
        id: 1,
        actionLabel: 'Role changed',
        actorUsername: null,
        actorRoleLabel: null,
        actorVia: null,
        before: { role: 'user' },
        after: { role: 'moderator' },
        createdAt: '2026-10-01T10:00:00+00:00',
        ...overrides,
    };
}

describe('AdminAuditTrailList', () => {
    it('names who acted: a username with role, the console, or the system', () => {
        const wrapper = mount(AdminAuditTrailList, {
            props: {
                entries: [
                    entry({ id: 3, actorUsername: 'warden', actorRoleLabel: 'Admin' }),
                    entry({ id: 2, actorVia: 'console' }),
                    entry({ id: 1 }),
                ],
            },
        });
        const lines = wrapper.findAll('li > p').map((p) => p.text());

        expect(lines[0]).toContain('Role changed by warden (Admin)');
        expect(lines[1]).toContain('by Console');
        expect(lines[2]).toContain('by System');
    });

    it('shows each change and keeps markup as text', () => {
        const payload = '<img src=x onerror=alert(1)>';
        const wrapper = mount(AdminAuditTrailList, { props: { entries: [entry({ before: null, after: { note: payload } })] } });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain(payload);
        expect(wrapper.text()).toContain('Added');
    });

    it('says so when nothing is logged', () => {
        expect(mount(AdminAuditTrailList, { props: { entries: [] } }).text()).toBe('Nothing has been logged about this account.');
    });
});
