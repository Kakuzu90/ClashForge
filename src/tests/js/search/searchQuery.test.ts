import { facetCounts, searchQuery, withoutMatch } from '@/Components/search/searchQuery';
import { describe, expect, it } from 'vitest';

const filters: App.Domain.Bases.Data.FeedFiltersData = { thMin: null, thMax: null, category: null, tag: null, minLikes: null, hasVideo: false, sort: 'relevance' };

const chips: App.Domain.Search.Data.ParsedFilterData[] = [
    { key: 'th', value: '17', label: 'Town Hall 17', match: 'TH17' },
    { key: 'category', value: 'war', label: 'War', match: 'war' },
];

describe('searchQuery', () => {
    it('keeps only the text on a tab, so the text filters apply again', () => {
        expect(searchQuery('TH17 war ring', 'all')).toEqual({ q: 'TH17 war ring' });
        expect(searchQuery('ring', 'players')).toEqual({ q: 'ring', type: 'players' });
    });

    it('writes Bases filters out and drops the words they were read from', () => {
        const query = searchQuery('TH17 war ring', 'bases', { ...filters, thMin: 17, thMax: 17, category: 'war', hasVideo: true }, chips);

        expect(query).toEqual({ q: 'ring', type: 'bases', th: '17', category: 'war', video: '1' });
    });

    it('leaves best match out of the URL and writes any other sort', () => {
        expect(searchQuery('ring', 'bases', filters)).toEqual({ q: 'ring', type: 'bases' });
        expect(searchQuery('ring', 'bases', { ...filters, sort: 'trending' })).toEqual({ q: 'ring', type: 'bases', sort: 'trending' });
    });
});

describe('withoutMatch', () => {
    it('removes the words in any case and tidies the spaces', () => {
        expect(withoutMatch('ring TH17 box', 'th17')).toBe('ring box');
        expect(withoutMatch('ring', 'war')).toBe('ring');
    });
});

describe('facetCounts', () => {
    it('maps counts by value', () => {
        expect(facetCounts([{ value: '16', count: 3 }])).toEqual({ 16: 3 });
        expect(facetCounts(undefined)).toEqual({});
    });
});
