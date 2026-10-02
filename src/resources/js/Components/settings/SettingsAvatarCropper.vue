<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiSpinner from '@/Components/ui/UiSpinner.vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, useId, watch } from 'vue';
import {
    clampCrop,
    cropRect,
    croppedFileName,
    decodeImage,
    drawLayout,
    encodeCrop,
    initialCrop,
    maxZoom,
    outputSide,
    panBy,
    zoomAt,
    type CropState,
    type DecodedImage,
} from './avatarCrop';

// Square crop before an avatar upload. A convenience only: the server still validates and re-encodes
// whatever arrives (specs/10 §4).
const props = defineProps<{
    /** The picked file; the dialog is open while this is set. */
    file: File | null;
    maxBytes: number;
    accept: string;
    /** Largest output edge in pixels. */
    outputSize: number;
    /** Smallest crop edge in source pixels that zooming may reach. */
    minSize: number;
}>();

const emit = defineEmits<{
    save: [file: File];
    cancel: [];
}>();

const KEY_PAN = 0.05;
const KEY_PAN_LARGE = 0.2;
const KEY_ZOOM = 0.1;

const status = ref<'loading' | 'ready' | 'saving' | 'error'>('loading');
const errorMessage = ref('');
const image = shallowRef<DecodedImage | null>(null);
const crop = ref<CropState>({ cx: 0, cy: 0, zoom: 1 });
const viewport = ref<HTMLElement | null>(null);
const preview = ref<HTMLCanvasElement | null>(null);
const hintId = useId();
const zoomId = useId();

const open = computed(() => props.file !== null);
const limit = computed(() => (image.value ? maxZoom(image.value, props.minSize) : 1));
const canZoom = computed(() => status.value === 'ready' && limit.value > 1);
const zoomPercent = computed(() => `${Math.round(crop.value.zoom * 100)}%`);

// Bumped on every new file and on cancel, so a late decode or encode cannot act on a closed dialog.
let run = 0;

watch(
    () => props.file,
    async (file) => {
        const current = ++run;
        release();
        if (!file) return;

        status.value = 'loading';
        errorMessage.value = '';
        try {
            const decoded = await decodeImage(file);
            if (current !== run) return decoded.release();
            image.value = decoded;
            crop.value = initialCrop(decoded);
            status.value = 'ready';
            await nextTick();
            draw();
        } catch {
            if (current !== run) return;
            errorMessage.value = 'This photo could not be opened. Choose a different one.';
            status.value = 'error';
        }
    },
    { immediate: true },
);

function release() {
    image.value?.release();
    image.value = null;
}

function update(next: CropState) {
    if (!image.value) return;
    crop.value = clampCrop(next, image.value, limit.value);
}

let frameRequest = 0;
watch(crop, () => {
    cancelAnimationFrame(frameRequest);
    frameRequest = requestAnimationFrame(draw);
});

function draw() {
    const canvas = preview.value;
    const decoded = image.value;
    if (!canvas || !decoded) return;

    const frame = canvas.clientWidth;
    const ratio = window.devicePixelRatio || 1;
    if (canvas.width !== Math.round(frame * ratio)) {
        canvas.width = Math.round(frame * ratio);
        canvas.height = Math.round(frame * ratio);
    }
    const context = canvas.getContext('2d');
    if (!context) return;

    const layout = drawLayout(crop.value, decoded, frame);
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.clearRect(0, 0, frame, frame);
    context.imageSmoothingQuality = 'high';
    context.drawImage(decoded.source, layout.x, layout.y, layout.width, layout.height);
}

let resizeObserver: ResizeObserver | null = null;
onMounted(() => {
    if (typeof ResizeObserver === 'undefined') return;
    resizeObserver = new ResizeObserver(() => draw());
    watch(
        preview,
        (canvas, previous) => {
            if (previous) resizeObserver?.unobserve(previous);
            if (canvas) resizeObserver?.observe(canvas);
        },
        { immediate: true },
    );
});

onBeforeUnmount(() => {
    run++;
    cancelAnimationFrame(frameRequest);
    resizeObserver?.disconnect();
    release();
});

function setZoom(zoom: number, fx = 0, fy = 0) {
    if (!image.value || status.value !== 'ready') return;
    crop.value = zoomAt(crop.value, image.value, limit.value, zoom, fx, fy);
}

function pan(dx: number, dy: number) {
    if (!image.value || status.value !== 'ready') return;
    crop.value = panBy(crop.value, image.value, limit.value, dx, dy);
}

function reset() {
    if (image.value) update(initialCrop(image.value));
}

// Pointer drag pans; two pointers pinch-zoom around their midpoint.
const pointers = new Map<number, { x: number; y: number }>();

function frameBox() {
    const box = viewport.value?.getBoundingClientRect();
    const size = box?.width || 1;
    return { left: box?.left ?? 0, top: box?.top ?? 0, size };
}

function focal(x: number, y: number) {
    const box = frameBox();
    return { fx: (x - box.left) / box.size - 0.5, fy: (y - box.top) / box.size - 0.5 };
}

function onPointerDown(event: PointerEvent) {
    if (status.value !== 'ready' || (event.pointerType === 'mouse' && event.button !== 0)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    try {
        viewport.value?.setPointerCapture(event.pointerId);
    } catch {
        // The pointer is already gone (or synthetic); the drag still works while it is over the frame.
    }
}

function onPointerMove(event: PointerEvent) {
    const last = pointers.get(event.pointerId);
    if (!last) return;
    const size = frameBox().size;

    if (pointers.size === 1) {
        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        pan((event.clientX - last.x) / size, (event.clientY - last.y) / size);
        return;
    }

    const [a, b] = [...pointers.values()];
    const before = Math.hypot(a.x - b.x, a.y - b.y);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    const [c, d] = [...pointers.values()];
    const after = Math.hypot(c.x - d.x, c.y - d.y);
    if (before > 0) {
        const mid = focal((c.x + d.x) / 2, (c.y + d.y) / 2);
        setZoom(crop.value.zoom * (after / before), mid.fx, mid.fy);
    }
}

function onPointerUp(event: PointerEvent) {
    pointers.delete(event.pointerId);
}

function onWheel(event: WheelEvent) {
    // Line-mode wheels (Firefox) report rows rather than pixels.
    const delta = event.deltaMode === 1 ? event.deltaY * 16 : event.deltaY;
    const point = focal(event.clientX, event.clientY);
    setZoom(crop.value.zoom * Math.exp(-delta * 0.002), point.fx, point.fy);
}

function onFrameKeydown(event: KeyboardEvent) {
    const step = event.shiftKey ? KEY_PAN_LARGE : KEY_PAN;
    const moves: Record<string, [number, number]> = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };
    const move = moves[event.key];
    if (!move) return;
    event.preventDefault();
    pan(...move);
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        setZoom(crop.value.zoom + KEY_ZOOM);
    } else if (event.key === '-' || event.key === '_') {
        event.preventDefault();
        setZoom(crop.value.zoom - KEY_ZOOM);
    }
}

function onSlider(event: Event) {
    setZoom(Number((event.target as HTMLInputElement).value));
}

async function save() {
    const decoded = image.value;
    const file = props.file;
    if (!decoded || !file || status.value !== 'ready') return;

    const current = run;
    status.value = 'saving';
    errorMessage.value = '';
    try {
        const rect = cropRect(crop.value, decoded);
        const blob = await encodeCrop(decoded.source, rect, {
            side: outputSide(rect.side, props.outputSize),
            maxBytes: props.maxBytes,
            accept: props.accept,
        });
        if (current !== run) return;
        status.value = 'ready';
        emit('save', new File([blob], croppedFileName(file.name, blob.type), { type: blob.type }));
    } catch {
        if (current !== run) return;
        errorMessage.value = 'The crop could not be saved. Try again, or choose a different photo.';
        status.value = 'ready';
    }
}

function cancel() {
    run++;
    pointers.clear();
    emit('cancel');
}

function onOpen(next: boolean) {
    if (!next) cancel();
}
</script>

<template>
    <UiModal :open="open" title="Crop your photo" description="Your photo shows as a circle across the site." @update:open="onOpen">
        <div class="flex flex-col gap-3" @keydown="onKeydown">
            <div
                ref="viewport"
                role="application"
                aria-label="Photo position"
                :aria-describedby="hintId"
                :tabindex="status === 'ready' ? 0 : -1"
                class="relative mx-auto aspect-square w-full max-w-[min(20rem,calc(100dvh-24rem))] min-w-40 touch-none overflow-hidden rounded-lg border border-line bg-page select-none"
                :class="status === 'ready' ? 'cursor-grab active:cursor-grabbing' : ''"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @lostpointercapture="onPointerUp"
                @wheel.prevent="onWheel"
                @keydown="onFrameKeydown"
            >
                <canvas v-show="image" ref="preview" class="absolute inset-0 size-full" aria-hidden="true" />
                <svg v-if="image" class="pointer-events-none absolute inset-0 size-full" viewBox="0 0 100 100" aria-hidden="true">
                    <path class="fill-scrim" fill-opacity="0.75" fill-rule="evenodd" d="M0 0H100V100H0Z M50 0a50 50 0 1 0 0 100a50 50 0 1 0 0-100Z" />
                    <circle class="stroke-fg" cx="50" cy="50" r="49.5" fill="none" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                </svg>
                <div v-if="status === 'loading'" class="absolute inset-0 flex items-center justify-center text-fg-secondary">
                    <UiSpinner :size="24" />
                    <span class="sr-only">Opening your photo</span>
                </div>
            </div>

            <p v-if="errorMessage" role="alert" class="text-sm text-danger-fg">{{ errorMessage }}</p>
            <p v-if="status !== 'error'" :id="hintId" class="text-center text-sm text-fg-secondary">
                Drag to move, scroll or pinch to zoom.<span class="sr-only"> Arrow keys move the photo, + and - zoom.</span>
            </p>

            <div class="flex items-center gap-2">
                <label :for="zoomId" class="sr-only">Zoom</label>
                <UiButton variant="ghost" size="sm" icon-only aria-label="Zoom out" :disabled="!canZoom" @click="setZoom(crop.zoom - KEY_ZOOM)">
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </UiButton>
                <input
                    :id="zoomId"
                    type="range"
                    class="h-11 min-w-0 flex-1 accent-brand disabled:cursor-not-allowed disabled:opacity-60"
                    min="1"
                    :max="limit"
                    step="0.01"
                    :value="crop.zoom"
                    :aria-valuetext="zoomPercent"
                    :disabled="!canZoom"
                    @input="onSlider"
                />
                <UiButton variant="ghost" size="sm" icon-only aria-label="Zoom in" :disabled="!canZoom" @click="setZoom(crop.zoom + KEY_ZOOM)">
                    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M3 8h10M8 3v10" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </UiButton>
                <UiButton variant="ghost" size="sm" :disabled="status !== 'ready'" @click="reset">Reset</UiButton>
            </div>
        </div>

        <template #footer>
            <!-- One row at every width, so the dialog fits short phone screens without scrolling. -->
            <div class="flex gap-2 sm:justify-end">
                <UiButton variant="ghost" class="flex-1 sm:flex-none" @click="cancel">Cancel</UiButton>
                <UiButton class="flex-1 sm:flex-none" :loading="status === 'saving'" :disabled="status !== 'ready'" @click="save">Save photo</UiButton>
            </div>
        </template>
    </UiModal>
</template>
