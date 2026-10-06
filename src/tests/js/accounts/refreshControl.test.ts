import { refreshHint, refreshState } from '@/Components/accounts/refreshControl';
import { describe, expect, it } from 'vitest';

describe('refreshControl', () => {
    it('picks the state: busy first, then the API, then the cooldown', () => {
        expect(refreshState({ processing: true, apiDown: true, waitSeconds: 30 })).toBe('refreshing');
        expect(refreshState({ processing: false, apiDown: true, waitSeconds: 30 })).toBe('unavailable');
        expect(refreshState({ processing: false, apiDown: false, waitSeconds: 30 })).toBe('cooling');
        expect(refreshState({ processing: false, apiDown: false, waitSeconds: 0 })).toBe('idle');
    });

    it('says how long the cooldown has left, in whole minutes rounded up', () => {
        expect(refreshHint('cooling', 600)).toBe('You can refresh again in 10 minutes.');
        expect(refreshHint('cooling', 61)).toBe('You can refresh again in 2 minutes.');
        expect(refreshHint('cooling', 59)).toBe('You can refresh again in 1 minute.');
        expect(refreshHint('cooling', 1)).toBe('You can refresh again in 1 minute.');
    });

    it('explains a paused refresh and says nothing otherwise', () => {
        expect(refreshHint('unavailable', 0)).toBe('Refresh is paused while the game API is unavailable.');
        expect(refreshHint('idle', 0)).toBeNull();
        expect(refreshHint('refreshing', 0)).toBeNull();
    });
});
