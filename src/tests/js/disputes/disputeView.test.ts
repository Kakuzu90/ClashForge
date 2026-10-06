import { disputeMessage, outcomeLink, statusTone } from '@/Components/disputes/disputeView';
import { describe, expect, it } from 'vitest';

type Dispute = App.Domain.PlayerAccounts.Data.PartyDisputeData;

const dispute = (overrides: Partial<Dispute> = {}): Dispute => ({
    ulid: '01J0000000000000000000DISP',
    tag: '#2PQ8GRJC',
    role: 'holder',
    status: 'open',
    statusLabel: 'Waiting for the holder',
    waitingOn: 'holder',
    deadline: '2026-10-13T12:00:00+00:00',
    openedAt: '2026-10-06T12:00:00+00:00',
    closedAt: null,
    outcome: null,
    outcomeLabel: null,
    accountUlid: '01J00000000000000000000ACC',
    submissions: [],
    evidenceLeft: 3,
    canRespond: true,
    canWithdraw: false,
    canRelease: true,
    canVerify: true,
    withdrawCountsTowardBar: false,
    ...overrides,
});

describe('disputeMessage', () => {
    it('asks the holder to answer by the deadline while the dispute waits on them', () => {
        const message = disputeMessage(dispute());

        expect(message.kind).toBe('warning');
        expect(message.title).toBe('Your answer is needed');
        expect(message.body).toContain('#2PQ8GRJC');
        expect(message.body).toContain('2026');
    });

    it('tells each side what it waits for, without naming the other party', () => {
        const cases: [Partial<Dispute>, string][] = [
            [{ role: 'claimant', waitingOn: 'holder', deadline: null }, 'Waiting for the holder'],
            [{ role: 'claimant', status: 'awaiting_holder', waitingOn: 'holder', deadline: null }, 'Waiting for the holder'],
            [{ role: 'claimant', status: 'awaiting_claimant', waitingOn: 'claimant' }, 'The admins need more from you'],
            [{ role: 'holder', status: 'awaiting_holder', waitingOn: 'holder' }, 'The admins need more from you'],
            [{ role: 'holder', status: 'awaiting_claimant', waitingOn: 'claimant', deadline: null }, 'Waiting for the other player'],
            [{ role: 'holder', status: 'awaiting_admin', waitingOn: 'admin', deadline: null }, 'The admins are reviewing the claim'],
            [{ role: 'claimant', status: 'awaiting_admin', waitingOn: 'admin', deadline: null }, 'The admins are reviewing your claim'],
        ];

        for (const [overrides, title] of cases) {
            const message = disputeMessage(dispute(overrides));
            expect(message.title).toBe(title);
            expect(message.body).not.toMatch(/@/);
        }
    });

    it('uses the server outcome as the title and adds what to do next', () => {
        const message = disputeMessage(
            dispute({ status: 'resolved_transfer', outcome: 'transferred_to_you', outcomeLabel: 'The account is now yours', role: 'claimant' }),
        );

        expect(message).toEqual({
            kind: 'success',
            title: 'The account is now yours',
            body: '#2PQ8GRJC is now verified on your Clash Commons account.',
        });
        expect(disputeMessage(dispute({ status: 'resolved_suspended', outcome: 'suspended', outcomeLabel: 'Suspended' })).kind).toBe('danger');
    });
});

describe('outcomeLink', () => {
    it('sends winners to their account and losers to the token path', () => {
        expect(outcomeLink(dispute())).toBeNull();
        expect(outcomeLink(dispute({ outcome: 'kept' }))).toBe('account');
        expect(outcomeLink(dispute({ outcome: 'released_to_you', accountUlid: null }))).toBeNull();
        expect(outcomeLink(dispute({ outcome: 'denied' }))).toBe('attach');
        expect(outcomeLink(dispute({ outcome: 'transferred_away' }))).toBe('attach');
        expect(outcomeLink(dispute({ outcome: 'withdrawn' }))).toBeNull();
    });
});

describe('statusTone', () => {
    it('marks a dispute with the admins as a warning and an ended one by its result', () => {
        expect(statusTone(dispute({ status: 'awaiting_admin' }))).toBe('warning');
        expect(statusTone(dispute({ status: 'resolved_transfer' }))).toBe('success');
        expect(statusTone(dispute({ status: 'resolved_suspended' }))).toBe('danger');
    });
});
