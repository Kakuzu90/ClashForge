import { feedQuery } from '@/Components/bases/feedQuery';

type Filters = App.Domain.Bases.Data.FeedFiltersData;
type Chip = App.Domain.Search.Data.ParsedFilterData;

export type SearchTab = 'all' | 'bases' | 'players' | 'accounts';

export const searchTabs: { key: SearchTab; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'bases', label: 'Bases' },
    { key: 'players', label: 'Players' },
    { key: 'accounts', label: 'Accounts' },
];

/** The text with one parsed filter's words taken out, spaces tidied. */
export function withoutMatch(q: string, match: string): string {
    const at = q.toLowerCase().indexOf(match.toLowerCase());
    if (at === -1) return q;
    return `${q.slice(0, at)} ${q.slice(at + match.length)}`.replace(/\s+/g, ' ').trim();
}

/**
 * The `/search` query string. A tab keeps only the text, so the text's filters apply again; the
 * Bases tab with `filters` writes them out explicitly and drops the words they were parsed from,
 * so a filter changed by hand is never re-read from the text. "Best match" is the default sort.
 */
export function searchQuery(q: string, tab: SearchTab, filters?: Filters, parsed: Chip[] = []): Record<string, string> {
    const text = filters ? parsed.reduce((rest, chip) => withoutMatch(rest, chip.match), q) : q;
    const query: Record<string, string> = text ? { q: text } : {};

    if (tab !== 'all') query.type = tab;
    if (tab === 'bases' && filters) {
        const { sort: _sort, ...rest } = feedQuery(filters);
        Object.assign(query, rest);
        if (filters.sort !== 'relevance') query.sort = filters.sort;
    }

    return query;
}

/** Facet counts by value, for labels. */
export function facetCounts(facets: App.Domain.Search.Data.FacetCountData[] | undefined): Record<string, number> {
    return Object.fromEntries((facets ?? []).map((facet) => [facet.value, facet.count]));
}
