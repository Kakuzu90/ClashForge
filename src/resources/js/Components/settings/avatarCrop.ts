// Pan and zoom math for SettingsAvatarCropper. Everything is in source-image pixels, so the result
// does not depend on how large the crop frame happens to be drawn on screen.

export interface CropSize {
    width: number;
    height: number;
}

/** Centre of the square crop in source pixels, and zoom (1 = the short side fills the frame). */
export interface CropState {
    cx: number;
    cy: number;
    zoom: number;
}

export interface CropRect {
    x: number;
    y: number;
    side: number;
}

/** Zooming past 4x only shows blur, whatever the source size. */
export const ZOOM_CAP = 4;

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

/** Side of the square crop in source pixels at this zoom. */
export function cropSide(size: CropSize, zoom: number): number {
    return Math.min(size.width, size.height) / zoom;
}

/**
 * Highest zoom that still leaves a crop of at least `minSide` source pixels, so zooming in never
 * produces an image below the upload minimum. Never below 1: the image always covers the frame.
 */
export function maxZoom(size: CropSize, minSide: number): number {
    return Math.max(1, Math.min(ZOOM_CAP, Math.min(size.width, size.height) / minSide));
}

export function initialCrop(size: CropSize): CropState {
    return { cx: size.width / 2, cy: size.height / 2, zoom: 1 };
}

/** Keeps zoom in [1, max] and the crop square inside the image, so the frame is always covered. */
export function clampCrop(state: CropState, size: CropSize, max: number): CropState {
    const zoom = clamp(state.zoom, 1, Math.max(1, max));
    const half = cropSide(size, zoom) / 2;

    return {
        zoom,
        cx: clamp(state.cx, half, size.width - half),
        cy: clamp(state.cy, half, size.height - half),
    };
}

/**
 * Moves the image by a fraction of the frame (dx 0.1 = a tenth of the frame to the right), the way a
 * drag does: the image follows the pointer, so the crop centre moves the other way.
 */
export function panBy(state: CropState, size: CropSize, max: number, dx: number, dy: number): CropState {
    const side = cropSide(size, state.zoom);

    return clampCrop({ ...state, cx: state.cx - dx * side, cy: state.cy - dy * side }, size, max);
}

/**
 * Zooms to `zoom` while keeping the source point under the focal point still. The focal point is an
 * offset from the frame centre as a fraction of the frame (-0.5 to 0.5); 0, 0 zooms on the centre.
 */
export function zoomAt(state: CropState, size: CropSize, max: number, zoom: number, fx = 0, fy = 0): CropState {
    const before = cropSide(size, state.zoom);
    const nextZoom = clamp(zoom, 1, Math.max(1, max));
    const after = cropSide(size, nextZoom);
    const px = state.cx + fx * before;
    const py = state.cy + fy * before;

    return clampCrop({ zoom: nextZoom, cx: px - fx * after, cy: py - fy * after }, size, max);
}

/** The square to copy out of the source image. */
export function cropRect(state: CropState, size: CropSize): CropRect {
    const side = cropSide(size, state.zoom);

    return { x: state.cx - side / 2, y: state.cy - side / 2, side };
}

/** Output edge in pixels: the crop's own resolution, capped at `maxOutput`; never upscaled. */
export function outputSide(side: number, maxOutput: number): number {
    return Math.max(1, Math.min(maxOutput, Math.round(side)));
}

/** Where to draw the whole source image inside a frame of `frame` pixels for the preview. */
export function drawLayout(state: CropState, size: CropSize, frame: number) {
    const scale = frame / cropSide(size, state.zoom);

    return {
        x: frame / 2 - state.cx * scale,
        y: frame / 2 - state.cy * scale,
        width: size.width * scale,
        height: size.height * scale,
    };
}

export interface DecodedImage extends CropSize {
    source: CanvasImageSource;
    release: () => void;
}

/** Decodes a picked file with its EXIF orientation applied. Client only. */
export async function decodeImage(file: Blob): Promise<DecodedImage> {
    if (typeof createImageBitmap === 'function') {
        try {
            const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
            return { source: bitmap, width: bitmap.width, height: bitmap.height, release: () => bitmap.close() };
        } catch {
            // Older engines reject the options bag; an <img> applies EXIF orientation by default.
        }
    }

    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        return { source: image, width: image.naturalWidth, height: image.naturalHeight, release: () => undefined };
    } finally {
        URL.revokeObjectURL(url);
    }
}

export interface EncodeOptions {
    /** Output edge in pixels. */
    side: number;
    maxBytes: number;
    /** MIME types the upload accepts, comma separated (the collection's `accept`). */
    accept: string;
}

const PREFERRED_TYPES = ['image/webp', 'image/jpeg'];
const QUALITIES = [0.9, 0.8, 0.7, 0.6, 0.5];

const toBlob = (canvas: HTMLCanvasElement, type: string, quality: number) =>
    new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, type, quality));

/**
 * Copies `rect` out of `source` into a square canvas and encodes it, preferring WebP and falling back
 * to JPEG where the browser cannot encode WebP. Quality steps down until the file fits `maxBytes`;
 * if nothing fits, the smallest attempt is returned and the server's size check has the last word.
 */
export async function encodeCrop(source: CanvasImageSource, rect: CropRect, options: EncodeOptions): Promise<Blob> {
    const accepted = options.accept.split(',').map((type) => type.trim());
    const types = PREFERRED_TYPES.filter((type) => accepted.includes(type));
    const canvas = document.createElement('canvas');
    canvas.width = options.side;
    canvas.height = options.side;
    const context = canvas.getContext('2d');
    if (!context || types.length === 0) {
        throw new Error('Cannot encode the crop in this browser.');
    }

    let smallest: Blob | null = null;
    for (const type of types) {
        context.clearRect(0, 0, options.side, options.side);
        if (type === 'image/jpeg') {
            // JPEG has no transparency; without a fill, transparent pixels turn black.
            context.fillStyle = 'white';
            context.fillRect(0, 0, options.side, options.side);
        }
        context.imageSmoothingQuality = 'high';
        context.drawImage(source, rect.x, rect.y, rect.side, rect.side, 0, 0, options.side, options.side);

        for (const quality of QUALITIES) {
            const blob = await toBlob(canvas, type, quality);
            // A browser that cannot encode this type silently returns PNG instead.
            if (!blob || blob.type !== type) break;
            if (blob.size <= options.maxBytes) return blob;
            if (!smallest || blob.size < smallest.size) smallest = blob;
        }
    }

    if (smallest) return smallest;
    throw new Error('Cannot encode the crop in this browser.');
}

/** "me.png" → "me.webp", keeping the user's name for the file. */
export function croppedFileName(original: string, type: string): string {
    const base = original.replace(/\.[^.]+$/, '') || 'avatar';
    return `${base}.${type === 'image/jpeg' ? 'jpg' : 'webp'}`;
}
