// Fire ring for a maxed unit's level chip: flames hug the chip outline on every side and lick
// upward, tallest above it and shortest below, so the chip reads as burning, not sitting on a fire.
// Loaded lazily by GameFireRing.vue; never imported during SSR.

// The chip sits in the middle of the canvas; GameFireRing.vue insets the canvas by MARGIN in CSS,
// so these must match the chip's size there.
const CHIP_WIDTH = 36;
const CHIP_HEIGHT = 20;
const MARGIN = 12;
const WIDTH = CHIP_WIDTH + MARGIN * 2;
const HEIGHT = CHIP_HEIGHT + MARGIN * 2;
const RADIUS = 6;
const FRAME_MS = 1000 / 30;

interface Target {
    context: CanvasRenderingContext2D;
    visible: boolean;
    mirror: boolean;
}

interface Renderer {
    add(canvas: HTMLCanvasElement): void;
    remove(canvas: HTMLCanvasElement): void;
}

// One heat field shared by every ring, so a full progression grid runs one simulation.
const targets = new Map<HTMLCanvasElement, Target>();
let renderer: Renderer | undefined;

function createRenderer(): Renderer {
    const source = document.createElement('canvas');
    source.width = WIDTH;
    source.height = HEIGHT;
    const context = source.getContext('2d')!;
    const pixels = context.createImageData(WIDTH, HEIGHT);
    const noise = Float32Array.from({ length: 1024 }, () => Math.random());
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let frame = 0;
    let tick = Math.random() * 1000;
    let lastTime = 0;

    // Per pixel, once: distance outside the rounded chip, and how far flame may reach there.
    const distance = new Float32Array(WIDTH * HEIGHT);
    const reach = new Float32Array(WIDTH * HEIGHT);
    const left = MARGIN;
    const right = WIDTH - 1 - MARGIN;
    const top = MARGIN;
    const bottom = HEIGHT - 1 - MARGIN;
    for (let y = 0; y < HEIGHT; y++) {
        for (let x = 0; x < WIDTH; x++) {
            const dx = Math.max(left + RADIUS - x, 0, x - (right - RADIUS));
            const dy = Math.max(top + RADIUS - y, 0, y - (bottom - RADIUS));
            distance[y * WIDTH + x] = Math.hypot(dx, dy) - RADIUS;
            const up = Math.min(1, Math.max(0, (bottom - y) / (bottom - top + MARGIN)));
            reach[y * WIDTH + x] = 2.5 + up * up * 9;
        }
    }

    // Ember → red → orange → yellow → white-hot, with alpha rising out of transparent smoke.
    const palette = new Uint8ClampedArray(256 * 4);
    for (let i = 0; i < 256; i++) {
        const t = i / 255;
        palette[i * 4] = Math.min(255, t * 3.2 * 255);
        palette[i * 4 + 1] = Math.max(0, Math.min(255, (t - 0.32) * 2.6 * 255));
        palette[i * 4 + 2] = Math.max(0, Math.min(255, (t - 0.8) * 5 * 255));
        const alpha = Math.min(1, Math.max(0, (t - 0.08) * 2.2));
        palette[i * 4 + 3] = alpha * alpha * (3 - 2 * alpha) * 255;
    }

    function sample(x: number, y: number): number {
        const column = Math.floor(x);
        const row = Math.floor(y);
        let horizontal = x - column;
        let vertical = y - row;
        horizontal *= horizontal * (3 - 2 * horizontal);
        vertical *= vertical * (3 - 2 * vertical);
        const a = noise[(row & 31) * 32 + (column & 31)];
        const b = noise[(row & 31) * 32 + ((column + 1) & 31)];
        const c = noise[((row + 1) & 31) * 32 + (column & 31)];
        const d = noise[((row + 1) & 31) * 32 + ((column + 1) & 31)];

        return (a + (b - a) * horizontal) * (1 - vertical) + (c + (d - c) * horizontal) * vertical;
    }

    function paint(): void {
        // Sampling at y + flow moves the noise upward, so tongues rise off every edge.
        const flow = ++tick * 0.16;
        const gain = 0.9 + sample(tick * 0.03, 11.7) * 0.25;
        for (let y = 0; y < HEIGHT; y++) {
            const edgeFade = Math.min(1, y / 5, (HEIGHT - 1 - y) / 3);
            for (let x = 0; x < WIDTH; x++) {
                const i = y * WIDTH + x;
                const sideFade = Math.min(1, x / 3, (WIDTH - 1 - x) / 3);
                const turbulence = sample(x * 0.2, y * 0.16 + flow) * 0.6 + sample(x * 0.45, y * 0.38 + flow * 1.7) * 0.4;
                const body = 1 - Math.max(0, distance[i]) / reach[i];
                const heat = Math.max(0, Math.min(1, (body * 1.15 + (turbulence - 0.5) * 1.3 - 0.12) * gain * edgeFade * sideFade));
                const index = Math.floor(heat * 255) * 4;
                const offset = i * 4;
                pixels.data[offset] = palette[index];
                pixels.data[offset + 1] = palette[index + 1];
                pixels.data[offset + 2] = palette[index + 2];
                pixels.data[offset + 3] = palette[index + 3];
            }
        }
        context.putImageData(pixels, 0, 0);
    }

    function draw(target: Target): void {
        target.context.clearRect(0, 0, WIDTH, HEIGHT);
        target.context.save();
        if (target.mirror) {
            target.context.translate(WIDTH, 0);
            target.context.scale(-1, 1);
        }
        target.context.drawImage(source, 0, 0);
        target.context.restore();
    }

    function animate(time: number): void {
        frame = 0;
        if (time - lastTime >= FRAME_MS) {
            paint();
            for (const target of targets.values()) if (target.visible) draw(target);
            lastTime = time;
        }
        resume();
    }

    // Runs only while a ring is on screen, the tab is visible and motion is allowed; reduced
    // motion leaves the last painted frame standing.
    function resume(): void {
        const active = !motion.matches && !document.hidden && [...targets.values()].some((target) => target.visible);
        if (active && !frame) frame = requestAnimationFrame(animate);
        if (!active && frame) {
            cancelAnimationFrame(frame);
            frame = 0;
        }
    }

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            const target = targets.get(entry.target as HTMLCanvasElement);
            if (target) {
                target.visible = entry.isIntersecting;
                if (target.visible) draw(target);
            }
        }
        resume();
    });
    motion.addEventListener('change', resume);
    document.addEventListener('visibilitychange', resume);
    paint();

    return {
        add(canvas) {
            const targetContext = canvas.getContext('2d');
            if (!targetContext) return;
            const target = { context: targetContext, visible: false, mirror: Math.random() > 0.5 };
            targets.set(canvas, target);
            draw(target);
            observer.observe(canvas);
        },
        remove(canvas) {
            observer.unobserve(canvas);
            targets.delete(canvas);
            resume();
        },
    };
}

export function fireRing(canvas: HTMLCanvasElement): () => void {
    renderer ??= createRenderer();
    renderer.add(canvas);

    return () => renderer?.remove(canvas);
}
