import UiToast from '@/Components/ui/UiToast.vue';
import { useToast } from '@/Composables/useToast';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('useToast', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => {
        useToast().clear();
        vi.useRealTimers();
    });

    it('auto-dismisses info toasts but keeps danger toasts', () => {
        const { push, toasts } = useToast();
        push('Saved');
        push('Failed', { kind: 'danger' });
        vi.advanceTimersByTime(6000);
        expect(toasts.value.map((t) => t.title)).toEqual(['Failed']);
    });

    it('shows at most three toasts', () => {
        const { push, toasts } = useToast();
        ['a', 'b', 'c', 'd'].forEach((t) => push(t, { timeout: 0 }));
        expect(toasts.value.map((t) => t.title)).toEqual(['b', 'c', 'd']);
    });

    it('announces danger as an alert and others as status', () => {
        expect(mount(UiToast, { props: { kind: 'danger', title: 'x' } }).attributes('role')).toBe('alert');
        expect(mount(UiToast, { props: { kind: 'reward', title: 'x' } }).attributes('role')).toBe('status');
    });
});

describe('useToast pausing', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => {
        useToast().clear();
        vi.useRealTimers();
    });

    it('freezes auto-dismiss while paused and resumes with the remaining time', () => {
        const { push, pause, resume, toasts } = useToast();
        push('Saved', { timeout: 5000 });
        vi.advanceTimersByTime(3000);
        pause();
        vi.advanceTimersByTime(10000);
        expect(toasts.value).toHaveLength(1);
        resume();
        vi.advanceTimersByTime(1999);
        expect(toasts.value).toHaveLength(1);
        vi.advanceTimersByTime(1);
        expect(toasts.value).toHaveLength(0);
    });
});
