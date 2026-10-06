<script setup lang="ts">
import UploadQueueItem from '@/Components/uploads/UploadQueueItem.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import { computed, ref, useId, watch } from 'vue';

// Up to `max` private evidence images for a dispute (specs/13 §5, specs/10 §3). Each upload starts as
// soon as it is picked; the model holds the ULIDs storage accepted, and `busy` stays true while any
// is still on its way, so the form can wait for them. The server checks owner, collection and count.
const props = defineProps<{
    label: string;
    hint: string;
    max: number;
    upload: App.Domain.Media.Data.UploadCollectionData;
    error?: string;
}>();

const ulids = defineModel<string[]>({ default: () => [] });
const busy = defineModel<boolean>('busy', { default: false });

interface Item {
    key: number;
    file: File;
    ulid: string | null;
    failed: boolean;
}

const items = ref<Item[]>([]);
const notice = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);
const hintId = useId();
const errorId = useId();
let next = 0;

const left = computed(() => props.max - items.value.length);
const megabytes = computed(() => `${Math.round((props.upload.maxBytes / 1024 / 1024) * 10) / 10} MB`);

watch(
    items,
    (current) => {
        ulids.value = current.flatMap((item) => (item.ulid ? [item.ulid] : []));
        busy.value = current.some((item) => item.ulid === null && !item.failed);
    },
    { deep: true },
);

function onFiles(event: Event) {
    const picked = Array.from((event.target as HTMLInputElement).files ?? []);
    (event.target as HTMLInputElement).value = '';
    notice.value =
        picked.length > left.value
            ? `Only ${left.value === 1 ? '1 more image fits' : `${left.value} more images fit`}, so the rest were left out.`
            : null;

    for (const file of picked.slice(0, Math.max(0, left.value))) {
        items.value.push({ key: next++, file, ulid: null, failed: false });
    }
}

function update(key: number, change: Partial<Item>) {
    const item = items.value.find((candidate) => candidate.key === key);
    if (item) Object.assign(item, change);
}

function remove(key: number) {
    items.value = items.value.filter((item) => item.key !== key);
    notice.value = null;
}

/** Empties the field after the form was sent. */
function clear() {
    items.value = [];
    notice.value = null;
}

defineExpose({ clear });
</script>

<template>
    <fieldset class="flex flex-col gap-3" :aria-describedby="[hintId, error ? errorId : ''].join(' ').trim()">
        <legend class="mb-1 text-sm font-medium text-fg">{{ label }}</legend>
        <p :id="hintId" class="text-sm text-fg-secondary">{{ hint }} {{ upload.typesLabel }}, up to {{ megabytes }} each, {{ max }} at most.</p>

        <ul v-if="items.length" class="flex flex-col gap-2">
            <UploadQueueItem
                v-for="item in items"
                :key="item.key"
                :file="item.file"
                :collection="upload.value"
                @ready="(ulid) => update(item.key, { ulid, failed: false })"
                @failed="update(item.key, { ulid: null, failed: true })"
                @remove="remove(item.key)"
            />
        </ul>

        <input ref="input" type="file" class="sr-only" tabindex="-1" aria-hidden="true" multiple :accept="upload.accept" @change="onFiles" />
        <UiButton v-if="left > 0" class="self-start" variant="secondary" size="sm" @click="input?.click()">
            {{ items.length ? 'Add another image' : 'Add images' }}
        </UiButton>
        <p v-else class="text-sm text-fg-secondary">That is the most images you can send.</p>
        <p v-if="notice" role="status" class="text-sm text-fg-secondary">{{ notice }}</p>
        <p v-if="error" :id="errorId" role="alert" class="text-sm text-danger-fg">{{ error }}</p>
    </fieldset>
</template>
