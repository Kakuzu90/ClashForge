import { complete, intent, show } from '@/actions/App/Http/Controllers/Upload/UploadController';
import type { RouteDefinition } from '@/wayfinder';
import { getCurrentScope, onScopeDispose, ref } from 'vue';

type UploadTicket = App.Domain.Media.Data.UploadTicketData;
type UploadStatus = App.Domain.Media.Data.UploadStatusData;
type MediaCollection = App.Domain.Media.Enums.MediaCollection;

export type UploadPhase = 'idle' | 'uploading' | 'processing' | 'slow' | 'ready' | 'failed' | 'unavailable';

export type PutFile = (ticket: UploadTicket, file: File, onProgress: (percent: number) => void) => Promise<void>;

export interface UploadOptions {
    /** Extra PUT attempts after the first fails (specs/10 §10: retry twice). */
    putRetries?: number;
    /** Wait before each status check, in ms; the last value repeats until maxPolls. */
    pollDelays?: number[];
    maxPolls?: number;
    put?: PutFile;
}

const messages = {
    network: 'The upload did not finish. Check your connection and try again.',
    generic: 'Something went wrong with this upload. Try again.',
    unavailable: 'Uploads are temporarily unavailable. Try again in a few minutes.',
};

const sleep = (ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms));

function xsrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}

// The only place app routes are called with fetch (specs/19 §2): uploads bypass Inertia because the
// bytes go straight to storage.
async function callApp(route: RouteDefinition<'post' | 'get'>, body?: unknown): Promise<Response> {
    return fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...xsrfHeader(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}

/** PUT with progress events, which fetch cannot report. */
export const xhrPut: PutFile = (ticket, file, onProgress) =>
    new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(ticket.uploadMethod, ticket.uploadUrl);
        Object.entries(ticket.uploadHeaders).forEach(([name, value]) => xhr.setRequestHeader(name, value));
        xhr.upload.onprogress = (event) => event.lengthComputable && onProgress((event.loaded / event.total) * 100);
        xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`PUT ${xhr.status}`)));
        xhr.onerror = () => reject(new Error('PUT network error'));
        xhr.send(file);
    });

async function errorMessage(response: Response): Promise<string> {
    try {
        const json = (await response.json()) as { message?: string; errors?: Record<string, string[]> };
        const first = json.errors ? Object.values(json.errors)[0]?.[0] : undefined;
        return first ?? json.message ?? messages.generic;
    } catch {
        return messages.generic;
    }
}

/**
 * Presigned upload flow (specs/10 §3): intent → PUT to storage → complete → poll until processed.
 */
export function useUpload(options: UploadOptions = {}) {
    const putRetries = options.putRetries ?? 2;
    const pollDelays = options.pollDelays ?? [1000, 1000, 2000, 3000, 5000];
    const maxPolls = options.maxPolls ?? 40;
    const put = options.put ?? xhrPut;

    const phase = ref<UploadPhase>('idle');
    const progress = ref(0);
    const error = ref<string | null>(null);
    const result = ref<UploadStatus | null>(null);
    const fileName = ref<string | null>(null);

    let run = 0;
    let last: { file: File; collection: MediaCollection } | null = null;
    let mediaUlid: string | null = null;

    if (getCurrentScope()) {
        onScopeDispose(() => run++);
    }

    function fail(message: string, next: UploadPhase = 'failed') {
        phase.value = next;
        error.value = message;
    }

    async function upload(file: File, collection: MediaCollection): Promise<void> {
        const current = ++run;
        last = { file, collection };
        mediaUlid = null;
        phase.value = 'uploading';
        progress.value = 0;
        error.value = null;
        result.value = null;
        fileName.value = file.name;

        try {
            const response = await callApp(intent(), { collection, filename: file.name, size: file.size, mime: file.type });
            if (current !== run) return;

            if (!response.ok) {
                return fail(await errorMessage(response), response.status === 503 ? 'unavailable' : 'failed');
            }

            const ticket = (await response.json()) as UploadTicket;
            mediaUlid = ticket.mediaUlid;

            for (let attempt = 0; ; attempt++) {
                try {
                    await put(ticket, file, (percent) => current === run && (progress.value = percent));
                    break;
                } catch {
                    if (current !== run) return;
                    if (attempt >= putRetries) return fail(messages.network);
                    progress.value = 0;
                }
            }
            if (current !== run) return;
            progress.value = 100;

            const completed = await callApp(complete(ticket.mediaUlid));
            if (current !== run) return;
            if (!completed.ok) return fail(await errorMessage(completed));

            apply((await completed.json()) as UploadStatus);
            await poll(current, ticket.mediaUlid);
        } catch {
            if (current === run) fail(messages.network);
        }
    }

    function apply(status: UploadStatus) {
        result.value = status;

        if (!status.finished) {
            phase.value = 'processing';
        } else if (status.status === 'ready') {
            phase.value = 'ready';
        } else {
            fail(status.failureMessage ?? messages.generic);
        }
    }

    async function poll(current: number, ulid: string): Promise<void> {
        for (let i = 0; i < maxPolls && phase.value === 'processing'; i++) {
            await sleep(pollDelays[Math.min(i, pollDelays.length - 1)]);
            if (current !== run) return;

            const response = await callApp(show(ulid));
            if (current !== run) return;
            if (!response.ok) return fail(await errorMessage(response));

            apply((await response.json()) as UploadStatus);
        }

        if (phase.value === 'processing') {
            phase.value = 'slow';
        }
    }

    /** Start the whole upload again with the same file. */
    function retry(): Promise<void> {
        return last ? upload(last.file, last.collection) : Promise.resolve();
    }

    /** Resume polling after processing took longer than maxPolls. */
    async function checkAgain(): Promise<void> {
        if (!mediaUlid) return;
        const current = ++run;
        phase.value = 'processing';
        await poll(current, mediaUlid);
    }

    function reset() {
        run++;
        phase.value = 'idle';
        progress.value = 0;
        error.value = null;
        result.value = null;
        fileName.value = null;
    }

    return { phase, progress, error, result, fileName, upload, retry, checkAgain, reset };
}
