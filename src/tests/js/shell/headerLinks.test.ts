import { formatDateTime } from '@/Composables/useDateTime';
import { visibleHeaderLinks, type HeaderLink } from '@/navigation';
import { describe, expect, it } from 'vitest';

const links: HeaderLink[] = [{ key: 'admin', label: 'Admin', href: () => '/admin', can: 'accessAdmin' }];

describe('header links', () => {
    it('shows a link only when the shared ability is true', () => {
        expect(visibleHeaderLinks(links, {})).toEqual([]);
        expect(visibleHeaderLinks(links, { accessAdmin: false })).toEqual([]);
        expect(visibleHeaderLinks(links, { accessAdmin: true }).map((l) => l.url)).toEqual(['/admin']);
    });
});

describe('formatDateTime', () => {
    it('formats an ISO timestamp with date and time', () => {
        const text = formatDateTime('2026-10-05T14:30:00+00:00', 'en-GB');

        expect(text).toContain('2026');
        expect(text).toContain('October');
    });
});
