import AdminSanctionHistory from '@/Components/admin/AdminSanctionHistory.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

type Sanction = App.Domain.Moderation.Data.SanctionData;

const sanction = (overrides: Partial<Sanction>): Sanction => ({
    typeLabel: 'Suspension',
    reasonLabel: 'Spam',
    publicReason: 'Spam links',
    internalNote: 'Forty links.',
    issuedBy: 'warden',
    startsAt: '2026-10-01T10:00:00+00:00',
    endsAt: '2026-10-08T10:00:00+00:00',
    state: 'active',
    stateLabel: 'Active',
    liftedBy: null,
    liftedAt: null,
    liftNote: null,
    ...overrides,
});

describe('AdminSanctionHistory', () => {
    it('shows what they were told, the note, and who lifted it and why', () => {
        const text = mount(AdminSanctionHistory, {
            props: {
                sanctions: [
                    sanction({
                        state: 'lifted',
                        stateLabel: 'Lifted',
                        liftedBy: 'boss',
                        liftedAt: '2026-10-02T10:00:00+00:00',
                        liftNote: 'Mistake.',
                    }),
                ],
            },
        }).text();

        expect(text).toContain('Spam links');
        expect(text).toContain('Forty links.');
        expect(text).toContain('by boss: Mistake.');
        expect(text).toContain('Lifted');
    });

    it('says when a ban has no end, and renders markup as text', () => {
        const payload = '<img src=x onerror=alert(1)>';
        const wrapper = mount(AdminSanctionHistory, { props: { sanctions: [sanction({ typeLabel: 'Ban', endsAt: null, publicReason: payload })] } });

        expect(wrapper.text()).toContain('No end date');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain(payload);
    });

    it('says so when there are none', () => {
        expect(mount(AdminSanctionHistory, { props: { sanctions: [] } }).text()).toBe('No sanctions on this account.');
    });
});
