/**
 * Which failed jobs a System Health action is about (P2-19): one job, a whole class, or the jobs
 * whose payload has no readable class. The server checks the target again.
 */
export type FailedJobTarget =
    { kind: 'job'; uuid: string; name: string | null } | { kind: 'class'; name: string; count: number } | { kind: 'unreadable'; count: number };

/** The request body for retry and delete. */
export function targetPayload(target: FailedJobTarget): Record<string, string | boolean> {
    switch (target.kind) {
        case 'job':
            return { uuid: target.uuid };
        case 'class':
            return { class: target.name };
        case 'unreadable':
            return { unreadable: true };
    }
}

/** The query that loads one group's job list. */
export function listQuery(name: string | null): Record<string, string | boolean> {
    return name === null ? { jobsUnreadable: true } : { jobsClass: name };
}

/** Unreadable payloads cannot be put back on a queue; they can only be deleted. */
export function canRetry(target: FailedJobTarget): boolean {
    return target.kind === 'class' || (target.kind === 'job' && target.name !== null);
}

/** What the delete confirmation says will go, capped like the server (`bulkMax`). */
export function deleteSummary(target: FailedJobTarget, bulkMax: number): string {
    if (target.kind === 'job') {
        return 'This failed job will be removed for good and will not run again.';
    }

    const count = Math.min(target.count, bulkMax);
    const jobs = count === 1 ? '1 failed job' : `${count} failed jobs`;
    const kind = target.kind === 'class' ? ` of ${target.name}` : ' with an unreadable payload';
    const rest = target.count > bulkMax ? ` That is the oldest ${bulkMax}; run it again for the other ${target.count - bulkMax}.` : '';

    return `${jobs}${kind} will be removed for good and will not run again.${rest}`;
}
