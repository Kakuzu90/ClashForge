import SettingsAvatarCropper from '@/Components/settings/SettingsAvatarCropper.vue';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

enableAutoUnmount(afterEach);

const bitmap = { width: 1600, height: 1000, close: vi.fn() };
const context = {
    drawImage: vi.fn(),
    clearRect: vi.fn(),
    fillRect: vi.fn(),
    setTransform: vi.fn(),
    fillStyle: '',
    imageSmoothingQuality: 'low',
};
// What each toBlob call produced the canvas at, and the blob it returns.
let encoded: Array<{ width: number; height: number; type: string; quality: number }> = [];
let blobFor = (type: string, quality: number) => new Blob([new Uint8Array(Math.round(quality * 100))], { type });

const props = { accept: 'image/jpeg,image/png,image/webp', maxBytes: 2 * 1024 * 1024, outputSize: 512, minSize: 200 };

async function openWith(file = new File(['x'], 'me.png', { type: 'image/png' }), overrides: Partial<typeof props> = {}) {
    const wrapper = mount(SettingsAvatarCropper, { props: { ...props, ...overrides, file: null }, attachTo: document.body });
    await wrapper.setProps({ file });
    await flushPromises();
    await nextTick();
    return wrapper;
}

const frame = () => document.querySelector('[aria-label="Photo position"]') as HTMLElement;
const slider = () => document.querySelector('input[type="range"]') as HTMLInputElement;
const button = (name: string) =>
    [...document.querySelectorAll('button')].find(
        (b) => b.textContent?.trim() === name || b.getAttribute('aria-label') === name,
    ) as HTMLButtonElement;
const press = (target: Element, key: string, init: KeyboardEventInit = {}) =>
    target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init }));
const cropCalls = () => context.drawImage.mock.calls.filter((call) => call.length === 9);

async function save(wrapper: Awaited<ReturnType<typeof openWith>>) {
    button('Save photo').click();
    await flushPromises();
    return wrapper.emitted('save')?.[0]?.[0] as File | undefined;
}

describe('SettingsAvatarCropper', () => {
    beforeEach(() => {
        encoded = [];
        blobFor = (type, quality) => new Blob([new Uint8Array(Math.round(quality * 100))], { type });
        Object.values(context).forEach((value) => typeof value === 'function' && (value as ReturnType<typeof vi.fn>).mockClear());
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(async () => bitmap),
        );
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(context as never);
        vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation(function (this: HTMLCanvasElement, callback, type = '', quality = 1) {
            encoded.push({ width: this.width, height: this.height, type, quality });
            callback(blobFor(type, quality));
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('opens a labelled dialog once a file is set and decodes with EXIF orientation', async () => {
        await openWith();

        expect(document.querySelector('[role="dialog"]')).not.toBeNull();
        expect(createImageBitmap).toHaveBeenCalledWith(expect.any(File), { imageOrientation: 'from-image' });
        expect(frame().getAttribute('tabindex')).toBe('0');
        expect(document.getElementById(frame().getAttribute('aria-describedby')!)?.textContent).toContain('Arrow keys');
        expect(document.querySelector(`label[for="${slider().id}"]`)?.textContent).toBe('Zoom');
        ['Zoom in', 'Zoom out', 'Reset', 'Cancel', 'Save photo'].forEach((name) => expect(button(name)).toBeDefined());
    });

    it('stays closed and decodes nothing without a file', () => {
        mount(SettingsAvatarCropper, { props: { ...props, file: null } });
        expect(document.querySelector('[role="dialog"]')).toBeNull();
        expect(createImageBitmap).not.toHaveBeenCalled();
    });

    it('saves the centred square at the largest variant size by default', async () => {
        const wrapper = await openWith();
        const file = await save(wrapper);

        expect(file).toBeInstanceOf(File);
        expect(file?.type).toBe('image/webp');
        expect(file?.name).toBe('me.webp');
        expect(encoded[0]).toMatchObject({ width: 512, height: 512, type: 'image/webp' });
        expect(cropCalls()[0]).toEqual([bitmap, 300, 0, 1000, 1000, 0, 0, 512, 512]);
    });

    it('never upscales a small crop', async () => {
        bitmap.width = 300;
        bitmap.height = 400;
        try {
            const wrapper = await openWith();
            await save(wrapper);
            expect(encoded[0]).toMatchObject({ width: 300, height: 300 });
            // Zooming stops where the crop would fall below the 200px minimum edge.
            expect(slider().max).toBe('1.5');
            expect(slider().disabled).toBe(false);
        } finally {
            bitmap.width = 1600;
            bitmap.height = 1000;
        }
    });

    it('turns zoom off for a photo below the minimum edge', async () => {
        bitmap.width = 150;
        bitmap.height = 180;
        try {
            await openWith();
            expect(slider().disabled).toBe(true);
            expect(button('Zoom in').disabled).toBe(true);
        } finally {
            bitmap.width = 1600;
            bitmap.height = 1000;
        }
    });

    it('pans with the arrow keys and stops at the image edge', async () => {
        const wrapper = await openWith();
        press(frame(), 'ArrowLeft');
        press(frame(), 'ArrowLeft');
        await save(wrapper);
        // Each press moves the photo a twentieth of the frame: the crop moves 50px right per press.
        expect(cropCalls()[0]?.slice(1, 5)).toEqual([400, 0, 1000, 1000]);

        press(frame(), 'ArrowLeft', { shiftKey: true });
        press(frame(), 'ArrowLeft', { shiftKey: true });
        press(frame(), 'ArrowDown');
        context.drawImage.mockClear();
        await save(wrapper);
        expect(cropCalls()[0]?.slice(1, 5)).toEqual([600, 0, 1000, 1000]);
    });

    it('pans with a drag and pinch-zooms with two pointers', async () => {
        const wrapper = await openWith();
        // jsdom has no layout, so the frame measures 1px and clientX is a fraction of the frame.
        const pointer = (type: string, pointerId: number, clientX: number, clientY = 0.5) =>
            frame().dispatchEvent(Object.assign(new Event(type, { bubbles: true }), { pointerId, clientX, clientY, pointerType: 'touch', button: 0 }));

        pointer('pointerdown', 1, 0.5);
        pointer('pointermove', 1, 0.6);
        pointer('pointerup', 1, 0.6);
        await save(wrapper);
        // Dragging right by a tenth of the frame moves the crop 100px left.
        expect(cropCalls()[0]?.slice(1, 5)).toEqual([200, 0, 1000, 1000]);

        pointer('pointerdown', 1, 0.45);
        pointer('pointerdown', 2, 0.55);
        pointer('pointermove', 2, 0.65);
        await nextTick();
        expect(Number(slider().value)).toBeCloseTo(2);
    });

    it('zooms with + and -, clamped to the range, and resets', async () => {
        await openWith();
        press(frame(), '+');
        press(frame(), '=');
        await nextTick();
        expect(Number(slider().value)).toBeCloseTo(1.2);

        press(slider(), '-');
        press(slider(), '-');
        press(slider(), '-');
        await nextTick();
        expect(Number(slider().value)).toBe(1);

        button('Zoom in').click();
        await nextTick();
        expect(Number(slider().value)).toBeCloseTo(1.1);

        button('Reset').click();
        await nextTick();
        expect(Number(slider().value)).toBe(1);
        expect(slider().getAttribute('aria-valuetext')).toBe('100%');
    });

    it('zooms from the slider and crops a smaller square', async () => {
        const wrapper = await openWith();
        slider().value = '2';
        slider().dispatchEvent(new Event('input'));
        await save(wrapper);

        expect(cropCalls()[0]).toEqual([bitmap, 550, 250, 500, 500, 0, 0, 500, 500]);
    });

    it('steps quality down until the file fits the upload limit', async () => {
        const wrapper = await openWith(undefined, { maxBytes: 70 });
        const file = await save(wrapper);

        expect(encoded.map((e) => e.quality)).toEqual([0.9, 0.8, 0.7]);
        expect(file?.size).toBe(70);
    });

    it('falls back to JPEG where the browser cannot encode WebP', async () => {
        blobFor = (type, quality) => new Blob([new Uint8Array(Math.round(quality * 100))], { type: type === 'image/webp' ? 'image/png' : type });
        const wrapper = await openWith();
        const file = await save(wrapper);

        expect(file?.type).toBe('image/jpeg');
        expect(file?.name).toBe('me.jpg');
    });

    it('emits nothing to upload on Cancel or Escape', async () => {
        const wrapper = await openWith();
        button('Cancel').click();
        expect(wrapper.emitted('cancel')).toHaveLength(1);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await nextTick();
        expect(wrapper.emitted('cancel')).toHaveLength(2);
        expect(wrapper.emitted('save')).toBeUndefined();
        expect(encoded).toEqual([]);
    });

    it('explains a photo that cannot be opened', async () => {
        vi.stubGlobal(
            'createImageBitmap',
            vi.fn(async () => Promise.reject(new Error('bad image'))),
        );
        vi.stubGlobal('URL', { ...URL, createObjectURL: () => 'blob:x', revokeObjectURL: vi.fn() });
        const decode = HTMLImageElement.prototype.decode;
        HTMLImageElement.prototype.decode = () => Promise.reject(new Error('bad image'));

        try {
            await openWith();
            expect(document.querySelector('[role="alert"]')?.textContent).toContain('could not be opened');
            expect(button('Save photo').disabled).toBe(true);
        } finally {
            HTMLImageElement.prototype.decode = decode;
        }
    });
});
