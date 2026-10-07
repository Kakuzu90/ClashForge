type Filters = App.Domain.Bases.Data.FeedFiltersData;

export type FeedQuery = Record<string, string>;

/** `16`, or `15-17` for a range (the signed-in default). */
export function thParam(min: number, max: number): string {
    return min === max ? `${min}` : `${min}-${max}`;
}

/**
 * The query string for a feed's filters, leaving out defaults so URLs stay short and shareable.
 * `allTh` writes `th=all`: on the home feed it overrides the signed-in Town Hall default.
 */
export function feedQuery(filters: Filters, options: { allTh?: boolean } = {}): FeedQuery {
    const query: FeedQuery = {};

    if (filters.thMin !== null && filters.thMax !== null) {
        query.th = thParam(filters.thMin, filters.thMax);
    } else if (options.allTh) {
        query.th = 'all';
    }
    if (filters.category) query.category = filters.category;
    if (filters.tag) query.tag = filters.tag;
    if (filters.minLikes) query.min_likes = String(filters.minLikes);
    if (filters.hasVideo) query.video = '1';
    if (filters.sort !== 'trending') query.sort = filters.sort;

    return query;
}

/** The filters with some fields changed. */
export function withFilters(filters: Filters, patch: Partial<Filters>): Filters {
    return { ...filters, ...patch };
}

/** How many filters (Town Hall, category, tag, likes, video) are set, for the mobile button's count. */
export function activeFilterCount(filters: Filters): number {
    return [filters.thMin !== null, filters.category !== null, filters.tag !== null, filters.minLikes !== null, filters.hasVideo].filter(Boolean)
        .length;
}

/** The Town Hall levels a chip row offers, highest first. */
export function thLevels(min: number, max: number): number[] {
    return Array.from({ length: max - min + 1 }, (_, i) => max - i);
}
