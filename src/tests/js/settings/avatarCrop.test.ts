import {
    clampCrop,
    cropRect,
    cropSide,
    croppedFileName,
    drawLayout,
    initialCrop,
    maxZoom,
    outputSide,
    panBy,
    ZOOM_CAP,
    zoomAt,
} from '@/Components/settings/avatarCrop';
import { describe, expect, it } from 'vitest';

const landscape = { width: 1600, height: 1000 };

describe('avatar crop math', () => {
    it('starts centred with the short side filling the frame', () => {
        const state = initialCrop(landscape);
        expect(state).toEqual({ cx: 800, cy: 500, zoom: 1 });
        expect(cropRect(state, landscape)).toEqual({ x: 300, y: 0, side: 1000 });
    });

    it('caps zoom so the crop never drops below the minimum edge', () => {
        expect(maxZoom(landscape, 200)).toBe(ZOOM_CAP);
        expect(maxZoom({ width: 600, height: 500 }, 200)).toBe(2.5);
        // Smaller than the minimum: zoom stays at 1, never below.
        expect(maxZoom({ width: 150, height: 150 }, 200)).toBe(1);
    });

    it('clamps zoom to its range and keeps the crop inside the image', () => {
        expect(clampCrop({ cx: 800, cy: 500, zoom: 0.5 }, landscape, 4).zoom).toBe(1);
        expect(clampCrop({ cx: 800, cy: 500, zoom: 9 }, landscape, 4).zoom).toBe(4);
        expect(clampCrop({ cx: -100, cy: 5000, zoom: 1 }, landscape, 4)).toEqual({ cx: 500, cy: 500, zoom: 1 });
        expect(clampCrop({ cx: 9999, cy: -1, zoom: 2 }, landscape, 4)).toEqual({ cx: 1350, cy: 250, zoom: 2 });
    });

    it('pans with the pointer and stops at the image edge', () => {
        const start = initialCrop(landscape);
        // Dragging right by a tenth of the frame shows more of the left side.
        expect(panBy(start, landscape, 4, 0.1, 0).cx).toBe(700);
        // At zoom 1 the short side has no room to move.
        expect(panBy(start, landscape, 4, 0, 0.3).cy).toBe(500);
        expect(panBy(start, landscape, 4, 5, 0).cx).toBe(500);
        expect(panBy(start, landscape, 4, -5, 0).cx).toBe(1100);
    });

    it('keeps the focal point still while zooming', () => {
        const start = initialCrop(landscape);
        const zoomed = zoomAt(start, landscape, 4, 2, 0.25, 0.25);
        // The source point under the focal point is the same before and after.
        const before = start.cx + 0.25 * cropSide(landscape, 1);
        const after = zoomed.cx + 0.25 * cropSide(landscape, 2);
        expect(after).toBeCloseTo(before);
        expect(zoomed.zoom).toBe(2);
    });

    it('re-clamps the centre when zooming out near an edge', () => {
        const edge = { cx: 1350, cy: 250, zoom: 2 };
        expect(zoomAt(edge, landscape, 4, 1)).toEqual({ cx: 1100, cy: 500, zoom: 1 });
    });

    it('never upscales the output and caps it at the largest variant', () => {
        expect(outputSide(1000, 512)).toBe(512);
        expect(outputSide(300.4, 512)).toBe(300);
    });

    it('draws the image so it always covers the frame', () => {
        const layout = drawLayout({ cx: 1350, cy: 250, zoom: 2 }, landscape, 320);
        expect(layout.x).toBeLessThanOrEqual(0);
        expect(layout.y).toBeLessThanOrEqual(0);
        expect(layout.x + layout.width).toBeGreaterThanOrEqual(320);
        expect(layout.y + layout.height).toBeGreaterThanOrEqual(320);
    });

    it('names the cropped file after the original', () => {
        expect(croppedFileName('holiday.photo.png', 'image/webp')).toBe('holiday.photo.webp');
        expect(croppedFileName('me.png', 'image/jpeg')).toBe('me.jpg');
        expect(croppedFileName('.png', 'image/webp')).toBe('avatar.webp');
    });
});
