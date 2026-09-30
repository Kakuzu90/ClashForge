import { readonly, ref } from 'vue';

export type ToastKind = 'info' | 'success' | 'danger' | 'reward';

export interface Toast {
    id: number;
    kind: ToastKind;
    title: string;
    body?: string;
}

export interface ToastOptions {
    kind?: ToastKind;
    body?: string;
    /** Milliseconds before auto-dismiss; 0 keeps it until dismissed. Danger toasts default to 0. */
    timeout?: number;
}

interface Timer {
    handle: ReturnType<typeof setTimeout> | null;
    remaining: number;
    startedAt: number;
}

const MAX_VISIBLE = 3;
const DEFAULT_TIMEOUT = 5000;

// Module-level queue shared by every caller. Toasts are only pushed from client-side events, and
// pushes are ignored during SSR so one request can never leak toasts into another.
const toasts = ref<Toast[]>([]);
const timers = new Map<number, Timer>();
let paused = false;
let nextId = 1;

function dismiss(id: number) {
    const timer = timers.get(id);
    if (timer?.handle) {
        clearTimeout(timer.handle);
    }
    timers.delete(id);
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function start(id: number, timer: Timer) {
    timer.startedAt = Date.now();
    timer.handle = setTimeout(() => dismiss(id), timer.remaining);
}

function push(title: string, options: ToastOptions = {}): number | null {
    if (import.meta.env.SSR) {
        return null;
    }

    const kind = options.kind ?? 'info';
    const toast: Toast = { id: nextId++, kind, title, body: options.body };
    const next = [...toasts.value, toast];
    next.slice(0, Math.max(0, next.length - MAX_VISIBLE)).forEach((old) => dismiss(old.id));
    toasts.value = next.slice(-MAX_VISIBLE);

    const timeout = options.timeout ?? (kind === 'danger' ? 0 : DEFAULT_TIMEOUT);
    if (timeout > 0) {
        const timer: Timer = { handle: null, remaining: timeout, startedAt: 0 };
        timers.set(toast.id, timer);
        if (!paused) {
            start(toast.id, timer);
        }
    }

    return toast.id;
}

/** Freeze auto-dismiss while the pointer or focus is inside the toast region. */
function pause() {
    if (paused) {
        return;
    }
    paused = true;
    timers.forEach((timer) => {
        if (timer.handle) {
            clearTimeout(timer.handle);
            timer.handle = null;
            timer.remaining -= Date.now() - timer.startedAt;
        }
    });
}

function resume() {
    if (!paused) {
        return;
    }
    paused = false;
    timers.forEach((timer, id) => start(id, timer));
}

export function useToast() {
    return {
        toasts: readonly(toasts),
        push,
        dismiss,
        pause,
        resume,
        clear: () => {
            [...timers.keys()].forEach(dismiss);
            toasts.value = [];
            paused = false;
        },
    };
}
