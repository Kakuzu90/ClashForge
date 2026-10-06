<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiModal from '@/Components/ui/UiModal.vue';
import UiSkeleton from '@/Components/ui/UiSkeleton.vue';
import UploadQueueItem from '@/Components/uploads/UploadQueueItem.vue';
import { destroy, store } from '@/routes/accounts/images';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// Custom images on an account page (FR-COC-11, specs/18 §6). Everyone sees the ready images as a
// grid that opens a viewer; the owner also gets their processing and failed ones, a remove button
// on each, and the upload queue. Each upload is added as soon as storage accepted it; the server
// checks owner, collection and count. Nothing renders for others when there are no images.
type Image = App.Domain.PlayerAccounts.Data.AccountImageData;

const props = defineProps<{
    accountUlid: string;
    accountName: string;
    images: Image[];
    canManage: boolean;
    max: number;
    upload: App.Domain.Media.Data.UploadCollectionData | null;
}>();

interface Queued {
    key: number;
    file: File;
    error: string | null;
    /** Set once its attach was sent: `ready` fires again when processing finishes. */
    sent: boolean;
}

const queue = ref<Queued[]>([]);
const notice = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);
let next = 0;

const ready = computed(() => props.images.filter((image) => image.full !== null && image.card !== null));
const left = computed(() => props.max - props.images.length - queue.value.length);
const megabytes = computed(() => (props.upload ? `${Math.round((props.upload.maxBytes / 1024 / 1024) * 10) / 10} MB` : ''));
const shown = computed(() => props.canManage || ready.value.length > 0);

function onFiles(event: Event) {
    const picked = Array.from((event.target as HTMLInputElement).files ?? []);
    (event.target as HTMLInputElement).value = '';
    const room = Math.max(0, left.value);
    notice.value = picked.length > room ? `Only ${room === 1 ? '1 more image fits' : `${room} more images fit`}, so the rest were left out.` : null;

    for (const file of picked.slice(0, room)) {
        queue.value.push({ key: next++, file, error: null, sent: false });
    }
}

function drop(key: number) {
    queue.value = queue.value.filter((item) => item.key !== key);
}

// Async visits, so several uploads finishing together (and the processing poll) do not cancel
// each other's requests.
function attach(key: number, media: string) {
    const item = queue.value.find((candidate) => candidate.key === key);
    if (!item || item.sent) return;
    item.sent = true;

    router.post(
        store(props.accountUlid).url,
        { media },
        {
            async: true,
            preserveScroll: true,
            onSuccess: () => drop(key),
            onError: (errors) => {
                item.error = errors.media ?? 'This image could not be added. Try again.';
            },
        },
    );
}

// Remove, after a confirm.
const removing = ref<Image | null>(null);
const confirmOpen = computed({
    get: () => removing.value !== null,
    set: (open: boolean) => {
        if (!open) removing.value = null;
    },
});
const busy = ref(false);
function remove() {
    if (removing.value === null) return;
    router.delete(destroy({ ulid: props.accountUlid, media: removing.value.ulid }).url, {
        async: true,
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => {
            busy.value = false;
            removing.value = null;
        },
    });
}

// Images still processing: reload the account until they finish, a bounded number of times.
let timer: ReturnType<typeof setTimeout> | null = null;
let polls = 0;
watch(
    () => props.images.some((image) => image.processing),
    (pending) => {
        if (timer) clearTimeout(timer);
        timer = null;
        if (!pending || polls >= 15) return;
        timer = setTimeout(() => {
            polls++;
            router.reload({ only: ['account'], async: true });
        }, 4000);
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});

// The viewer: previous / next with buttons or arrow keys, wrapping round.
const viewing = ref<number | null>(null);
const viewerOpen = computed({
    get: () => viewing.value !== null,
    set: (open: boolean) => {
        if (!open) viewing.value = null;
    },
});
const current = computed(() => (viewing.value === null ? null : (ready.value[viewing.value] ?? null)));
const viewerTitle = computed(() => (viewing.value === null ? '' : `${props.accountName}, image ${viewing.value + 1} of ${ready.value.length}`));
function step(by: number) {
    if (viewing.value === null || ready.value.length === 0) return;
    viewing.value = (viewing.value + by + ready.value.length) % ready.value.length;
}
function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') step(1);
    else if (event.key === 'ArrowLeft') step(-1);
}
// Arrow keys work wherever focus sits in the open viewer (it starts on the close button).
watch(viewerOpen, (open) => (open ? window.addEventListener('keydown', onKey) : window.removeEventListener('keydown', onKey)));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <section v-if="shown" aria-labelledby="account-images" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="account-images" class="font-display text-h2 text-fg">Images</h2>
            <p v-if="canManage" class="text-sm text-fg-secondary">{{ images.length }} of {{ max }} images</p>
        </div>

        <ul v-if="images.length" class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <li v-for="image in images" :key="image.ulid" class="relative">
                <button
                    v-if="image.card && image.full"
                    type="button"
                    class="block w-full overflow-hidden rounded-md border border-line bg-surface-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    :aria-label="`Open image ${ready.indexOf(image) + 1} of ${ready.length}`"
                    @click="viewing = ready.indexOf(image)"
                >
                    <img
                        :src="image.card.url"
                        :width="image.card.width"
                        :height="image.card.height"
                        :alt="`${accountName}, image ${ready.indexOf(image) + 1} of ${ready.length}`"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover"
                    />
                </button>
                <div v-else-if="image.processing" class="aspect-[4/3] overflow-hidden rounded-md border border-line">
                    <UiSkeleton variant="card" label="Processing image" />
                </div>
                <div v-else class="grid aspect-[4/3] place-items-center rounded-md border border-danger bg-surface-raised p-3 text-center">
                    <p class="text-sm text-fg-secondary">This image could not be used.</p>
                </div>
                <UiButton
                    v-if="canManage"
                    class="absolute top-2 right-2"
                    size="sm"
                    variant="secondary"
                    :aria-label="image.full ? `Remove image ${ready.indexOf(image) + 1}` : 'Remove this upload'"
                    @click="removing = image"
                >
                    Remove
                </UiButton>
            </li>
        </ul>

        <template v-if="canManage && upload">
            <ul v-if="queue.length" class="flex flex-col gap-2">
                <UploadQueueItem
                    v-for="item in queue"
                    :key="item.key"
                    :file="item.file"
                    :collection="upload.value"
                    :refused="item.error"
                    @ready="(media) => attach(item.key, media)"
                    @remove="drop(item.key)"
                />
            </ul>
            <div
                v-if="left > 0"
                class="flex flex-col gap-2 rounded-md border-2 border-dashed border-line-strong p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-fg-secondary">
                    {{ images.length === 0 ? 'Show your base, your heroes or a proud moment.' : 'Add more images.' }}
                    {{ upload.typesLabel }}, up to {{ megabytes }} each.
                </p>
                <input ref="input" type="file" class="sr-only" tabindex="-1" aria-hidden="true" multiple :accept="upload.accept" @change="onFiles" />
                <UiButton class="self-start sm:self-auto" variant="secondary" size="sm" @click="input?.click()">Add images</UiButton>
            </div>
            <p v-else-if="queue.length === 0" class="text-sm text-fg-secondary">
                This account has the most images it can have. Remove one to add another.
            </p>
            <p v-if="notice" role="status" class="text-sm text-fg-secondary">{{ notice }}</p>
        </template>

        <UiModal v-model:open="viewerOpen" :title="viewerTitle" wide>
            <div v-if="current && current.full" class="flex flex-col gap-4">
                <img
                    :src="current.full.url"
                    :width="current.full.width"
                    :height="current.full.height"
                    :alt="viewerTitle"
                    class="max-h-[65dvh] w-full rounded-md object-contain"
                />
                <div v-if="ready.length > 1" class="flex justify-between gap-2">
                    <UiButton variant="secondary" size="sm" @click="step(-1)">Previous</UiButton>
                    <UiButton variant="secondary" size="sm" @click="step(1)">Next</UiButton>
                </div>
            </div>
        </UiModal>

        <UiModal v-model:open="confirmOpen" title="Remove this image?" description="It is deleted for good.">
            <template #footer="{ close }">
                <UiButton variant="ghost" @click="close">Keep it</UiButton>
                <UiButton variant="danger" :loading="busy" @click="remove">Remove image</UiButton>
            </template>
        </UiModal>
    </section>
</template>
