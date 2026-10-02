import { formatDuration } from '@/Composables/useDateTime';
import { describe, expect, it } from 'vitest';

describe('formatDuration', () => {
    it('uses the two largest units, dropping a zero remainder', () => {
        expect(formatDuration(0)).toBe('0 s');
        expect(formatDuration(45)).toBe('45 s');
        expect(formatDuration(60)).toBe('1 min');
        expect(formatDuration(299)).toBe('4 min');
        expect(formatDuration(3600)).toBe('1 h');
        expect(formatDuration(11_100)).toBe('3 h 5 min');
        expect(formatDuration(187_200)).toBe('2 d 4 h');
        expect(formatDuration(86_400)).toBe('1 d');
    });

    it('never shows a negative or fractional length', () => {
        expect(formatDuration(-5)).toBe('0 s');
        expect(formatDuration(59.9)).toBe('59 s');
    });
});
