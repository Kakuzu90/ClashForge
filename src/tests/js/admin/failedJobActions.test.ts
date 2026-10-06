import { canRetry, deleteSummary, listQuery, targetPayload } from '@/Components/admin/failedJobActions';
import { describe, expect, it } from 'vitest';

describe('failedJobActions', () => {
    it('sends exactly one target', () => {
        expect(targetPayload({ kind: 'job', uuid: 'abc', name: 'SyncJob' })).toEqual({ uuid: 'abc' });
        expect(targetPayload({ kind: 'class', name: 'SyncJob', count: 2 })).toEqual({ class: 'SyncJob' });
        expect(targetPayload({ kind: 'unreadable', count: 2 })).toEqual({ unreadable: true });
        expect(listQuery(null)).toEqual({ jobsUnreadable: true });
        expect(listQuery('SyncJob')).toEqual({ jobsClass: 'SyncJob' });
    });

    it('never offers a retry for an unreadable payload', () => {
        expect(canRetry({ kind: 'unreadable', count: 1 })).toBe(false);
        expect(canRetry({ kind: 'job', uuid: 'abc', name: null })).toBe(false);
        expect(canRetry({ kind: 'job', uuid: 'abc', name: 'SyncJob' })).toBe(true);
    });

    it('says how many a delete removes, capped like the server', () => {
        expect(deleteSummary({ kind: 'class', name: 'SyncJob', count: 3 }, 200)).toBe('3 failed jobs of SyncJob will be removed for good and will not run again.');
        expect(deleteSummary({ kind: 'class', name: 'SyncJob', count: 250 }, 200)).toBe(
            '200 failed jobs of SyncJob will be removed for good and will not run again. That is the oldest 200; run it again for the other 50.',
        );
        expect(deleteSummary({ kind: 'job', uuid: 'abc', name: null }, 200)).toBe('This failed job will be removed for good and will not run again.');
    });
});
