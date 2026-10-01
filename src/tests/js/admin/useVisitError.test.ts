import { useVisitError } from '@/Composables/useVisitError';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

type Handler = (event: { detail: Record<string, unknown>; preventDefault: () => void }) => void;
const handlers: Record<string, Handler> = {};

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (name: string, handler: Handler) => {
            handlers[name] = handler;
            return () => delete handlers[name];
        },
    },
}));

function setup() {
    let error!: ReturnType<typeof useVisitError>;
    mount(
        defineComponent({
            setup() {
                error = useVisitError();
                return () => h('div');
            },
        }),
    );
    return () => error.value;
}

function fire(name: string, detail: Record<string, unknown>) {
    const preventDefault = vi.fn();
    handlers[name]?.({ detail, preventDefault });
    return preventDefault;
}

describe('useVisitError', () => {
    it('keeps a server error inline with its request id', () => {
        const error = setup();
        const prevented = fire('invalid', { response: { status: 500, headers: { 'x-request-id': 'req-123' } } });

        expect(prevented).toHaveBeenCalled();
        expect(error()).toEqual({ requestId: 'req-123' });
    });

    it('leaves other invalid responses to Inertia', () => {
        const error = setup();
        const prevented = fire('invalid', { response: { status: 419, headers: {} } });

        expect(prevented).not.toHaveBeenCalled();
        expect(error()).toBeNull();
    });

    it('reports a network failure without a request id and clears on the next visit', () => {
        const error = setup();
        fire('exception', { exception: new Error('offline') });
        expect(error()).toEqual({ requestId: null });

        fire('start', {});
        expect(error()).toBeNull();
    });
});
