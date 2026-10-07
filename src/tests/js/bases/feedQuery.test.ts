import { activeFilterCount, feedQuery, thLevels, thParam, withFilters } from '@/Components/bases/feedQuery';
import { describe, expect, it } from 'vitest';

const none: App.Domain.Bases.Data.FeedFiltersData = { thMin: null, thMax: null, category: null, tag: null, minLikes: null, hasVideo: false, sort: 'trending' };

describe('feedQuery', () => {
    it('leaves defaults out of the URL', () => {
        expect(feedQuery(none)).toEqual({});
    });

    it('writes every set filter in the names the server reads', () => {
        expect(
            feedQuery({ thMin: 15, thMax: 17, category: 'anti_3_star', tag: 'ring-base', minLikes: 50, hasVideo: true, sort: 'liked' }),
        ).toEqual({ th: '15-17', category: 'anti_3_star', tag: 'ring-base', min_likes: '50', video: '1', sort: 'liked' });
    });

    it('asks for every Town Hall explicitly when told to, to override the signed-in default', () => {
        expect(feedQuery(none, { allTh: true })).toEqual({ th: 'all' });
        expect(feedQuery(withFilters(none, { thMin: 16, thMax: 16 }), { allTh: true })).toEqual({ th: '16' });
    });

    it('formats one level or a range', () => {
        expect(thParam(16, 16)).toBe('16');
        expect(thParam(15, 17)).toBe('15-17');
    });

    it('counts the filters set, not the sort', () => {
        expect(activeFilterCount(withFilters(none, { sort: 'new' }))).toBe(0);
        expect(activeFilterCount(withFilters(none, { thMin: 16, thMax: 16, hasVideo: true, tag: 'box' }))).toBe(3);
    });

    it('lists Town Hall levels highest first', () => {
        expect(thLevels(14, 17)).toEqual([17, 16, 15, 14]);
    });
});
