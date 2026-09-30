import { useUpload, type PutFile } from '@/Composables/useUpload';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

type Status = App.Domain.Media.Data.UploadStatusData;

const ticket: App.Domain.Media.Data.UploadTicketData = {
    mediaUlid: '01j0000000000000000000000a',
    uploadUrl: 'https://storage.test/quarantine/x/original.jpg?signature=1',
    uploadMethod: 'PUT',
    uploadHeaders: { 'Content-Type': 'image/jpeg' },
    expiresIn: 300,
    maxSize: 5_000_000,
};

const status = (overrides: Partial<Status> = {}): Status => ({
    mediaUlid: ticket.mediaUlid,
    status: 'uploaded',
    finished: false,
    failureMessage: null,
    width: null,
    height: null,
    variants: [],
    ...overrides,
});

const ready = status({
    status: 'ready',
    finished: true,
    width: 1200,
    height: 900,
    variants: [{ name: 'thumb', url: 'https://cdn.test/t.webp', width: 320, height: 240 }],
});

const json = (body: unknown, init: number = 200) =>
    new Response(JSON.stringify(body), { status: init, headers: { 'Content-Type': 'application/json' } });

function file() {
    return new File([new Uint8Array(10)], 'base.jpg', { type: 'image/jpeg' });
}

describe('useUpload', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        document.cookie = 'XSRF-TOKEN=abc%3D';
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('runs intent, PUT, complete and polls until ready', async () => {
        const put = vi.fn<PutFile>(async (_t, _f, onProgress) => onProgress(50));
        fetchMock
            .mockResolvedValueOnce(json(ticket, 201))
            .mockResolvedValueOnce(json(status(), 202))
            .mockResolvedValueOnce(json(status({ status: 'processing' })))
            .mockResolvedValueOnce(json(ready));

        const upload = useUpload({ put, pollDelays: [10] });
        const done = upload.upload(file(), 'base_screenshot');
        await vi.runAllTimersAsync();
        await done;

        expect(upload.phase.value).toBe('ready');
        expect(upload.result.value?.variants).toHaveLength(1);
        expect(put).toHaveBeenCalledWith(ticket, expect.any(File), expect.any(Function));

        const [intentUrl, intentInit] = fetchMock.mock.calls[0];
        expect(intentUrl).toBe('/uploads/intent');
        expect(intentInit.method).toBe('POST');
        expect(intentInit.headers['X-XSRF-TOKEN']).toBe('abc=');
        expect(JSON.parse(intentInit.body)).toEqual({ collection: 'base_screenshot', filename: 'base.jpg', size: 10, mime: 'image/jpeg' });
        expect(fetchMock.mock.calls[1][0]).toBe(`/uploads/${ticket.mediaUlid}/complete`);
        expect(fetchMock.mock.calls[2][0]).toBe(`/uploads/${ticket.mediaUlid}`);
    });

    it('retries the PUT twice, then fails with a retry available', async () => {
        const put = vi.fn<PutFile>().mockRejectedValue(new Error('offline'));
        fetchMock.mockResolvedValueOnce(json(ticket, 201));

        const upload = useUpload({ put });
        await upload.upload(file(), 'base_screenshot');

        expect(put).toHaveBeenCalledTimes(3);
        expect(upload.phase.value).toBe('failed');
        expect(upload.error.value).toContain('did not finish');
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('recovers when a retried PUT succeeds', async () => {
        const put = vi.fn<PutFile>().mockRejectedValueOnce(new Error('blip')).mockResolvedValueOnce(undefined);
        fetchMock.mockResolvedValueOnce(json(ticket, 201)).mockResolvedValueOnce(json(ready, 202));

        const upload = useUpload({ put });
        await upload.upload(file(), 'base_screenshot');

        expect(put).toHaveBeenCalledTimes(2);
        expect(upload.phase.value).toBe('ready');
    });

    it('shows the first validation message from the intent', async () => {
        fetchMock.mockResolvedValueOnce(json({ message: 'invalid', errors: { size: ['This file is larger than 5 MB.'] } }, 422));

        const upload = useUpload({ put: vi.fn() });
        await upload.upload(file(), 'base_screenshot');

        expect(upload.phase.value).toBe('failed');
        expect(upload.error.value).toBe('This file is larger than 5 MB.');
    });

    it('switches to the unavailable state on 503', async () => {
        fetchMock.mockResolvedValueOnce(json({ message: 'Uploads are temporarily unavailable. Try again in a few minutes.' }, 503));

        const upload = useUpload({ put: vi.fn() });
        await upload.upload(file(), 'base_screenshot');

        expect(upload.phase.value).toBe('unavailable');
    });

    it('surfaces the server failure reason', async () => {
        fetchMock
            .mockResolvedValueOnce(json(ticket, 201))
            .mockResolvedValueOnce(json(status({ status: 'failed', finished: true, failureMessage: 'This image is too small.' }), 202));

        const upload = useUpload({ put: vi.fn<PutFile>(async () => undefined) });
        await upload.upload(file(), 'base_screenshot');

        expect(upload.phase.value).toBe('failed');
        expect(upload.error.value).toBe('This image is too small.');
    });

    it('stops polling after maxPolls and can check again', async () => {
        fetchMock
            .mockResolvedValueOnce(json(ticket, 201))
            .mockResolvedValueOnce(json(status(), 202))
            .mockResolvedValueOnce(json(status({ status: 'processing' })))
            .mockResolvedValueOnce(json(status({ status: 'processing' })))
            .mockResolvedValueOnce(json(ready));

        const upload = useUpload({ put: vi.fn<PutFile>(async () => undefined), pollDelays: [10], maxPolls: 2 });
        const done = upload.upload(file(), 'base_screenshot');
        await vi.runAllTimersAsync();
        await done;

        expect(upload.phase.value).toBe('slow');

        const again = upload.checkAgain();
        await vi.runAllTimersAsync();
        await again;

        expect(upload.phase.value).toBe('ready');
    });

    it('starts over with the same file on retry', async () => {
        const put = vi.fn<PutFile>().mockRejectedValue(new Error('offline'));
        fetchMock.mockImplementation(async () => json(ticket, 201));

        const upload = useUpload({ put, putRetries: 0 });
        await upload.upload(file(), 'avatar');
        await upload.retry();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(put).toHaveBeenCalledTimes(2);
        expect(upload.error.value).toContain('did not finish');
        expect(JSON.parse(fetchMock.mock.calls[1][1].body).collection).toBe('avatar');
    });
});
