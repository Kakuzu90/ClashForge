<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiCard from '@/Components/ui/UiCard.vue';
import UiEmptyState from '@/Components/ui/UiEmptyState.vue';
import UiProgress from '@/Components/ui/UiProgress.vue';
import UiSelect, { type SelectOption } from '@/Components/ui/UiSelect.vue';
import { useUpload } from '@/Composables/useUpload';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ collections: App.Domain.Media.Data.UploadCollectionData[] }>();

type Collection = App.Domain.Media.Enums.MediaCollection;

const collection = ref<Collection | null>(props.collections[0]?.value ?? null);
const options = computed<SelectOption<Collection>[]>(() => props.collections.map((c) => ({ value: c.value, label: c.label })));
const selected = computed(() => props.collections.find((c) => c.value === collection.value));
const fileInput = ref<HTMLInputElement | null>(null);

const { phase, progress, error, result, fileName, upload, retry, checkAgain } = useUpload();
const busy = computed(() => phase.value === 'uploading' || phase.value === 'processing');

function megabytes(bytes: number): string {
    return `${Math.round((bytes / 1024 / 1024) * 10) / 10} MB`;
}

function onFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file && collection.value) {
        upload(file, collection.value);
    }
    // Picking the same file twice should start a new upload.
    input.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-6 py-6">
        <header class="flex flex-col gap-2">
            <p class="text-xs font-semibold text-fg-muted uppercase">Dev only</p>
            <h1 class="font-display text-h1">Media upload check</h1>
            <p class="max-w-prose text-body text-fg-secondary">
                Sends one image through the whole pipeline: a signed upload to storage, processing on the media queue, then the resized versions it
                stored. You are signed in as a local test account.
            </p>
        </header>

        <UiCard variant="flat" class="flex flex-col gap-4 p-4 sm:flex-row sm:items-end">
            <UiSelect
                v-model="collection"
                label="Collection"
                searchable
                :options="options"
                :disabled="busy"
                :hint="selected ? `${selected.typesLabel}, up to ${megabytes(selected.maxBytes)}` : undefined"
                class="sm:w-72"
            />
            <div>
                <input ref="fileInput" type="file" class="sr-only" tabindex="-1" aria-hidden="true" :accept="selected?.accept" @change="onFile" />
                <UiButton :loading="busy" @click="fileInput?.click()">Choose image</UiButton>
            </div>
        </UiCard>

        <section aria-labelledby="result-heading" class="flex flex-col gap-4">
            <h2 id="result-heading" class="sr-only">Upload result</h2>

            <div aria-live="polite" class="flex flex-col gap-4">
                <UiEmptyState v-if="phase === 'idle'" title="No upload yet" body="Choose an image to see each variant the pipeline stores for it." />

                <UiProgress v-else-if="phase === 'uploading'" :label="`Uploading ${fileName}`" :value="progress" />

                <div v-else-if="phase === 'processing'" class="flex flex-col gap-2">
                    <UiProgress label="Processing" />
                    <p class="text-sm text-fg-secondary">Checking the file and making the resized versions.</p>
                </div>

                <UiCard v-else-if="phase === 'slow'" variant="flat" class="flex flex-col items-start gap-3 p-4">
                    <p class="text-body text-fg">This is taking longer than usual. Is the media queue worker running?</p>
                    <UiButton variant="secondary" @click="checkAgain">Check again</UiButton>
                </UiCard>

                <UiCard
                    v-else-if="phase === 'failed' || phase === 'unavailable'"
                    variant="flat"
                    class="flex flex-col items-start gap-3 border-l-4 border-l-danger p-4"
                    role="alert"
                >
                    <p class="text-body text-fg">{{ error }}</p>
                    <UiButton v-if="phase === 'failed'" variant="secondary" @click="retry">Try again</UiButton>
                </UiCard>

                <template v-else-if="phase === 'ready' && result">
                    <p class="text-body text-fg">
                        Ready. Original {{ result.width }} × {{ result.height }}, {{ result.variants.length }} variants stored.
                    </p>
                    <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <li v-for="variant in result.variants" :key="variant.name">
                            <UiCard variant="raised" as="figure" class="flex flex-col gap-2 overflow-hidden p-3">
                                <img
                                    :src="variant.url"
                                    :width="variant.width"
                                    :height="variant.height"
                                    :alt="`${variant.name} variant of ${fileName}`"
                                    loading="lazy"
                                    class="h-auto max-w-full self-start rounded-sm"
                                />
                                <figcaption class="text-sm text-fg-secondary">
                                    <span class="font-semibold text-fg">{{ variant.name }}</span>
                                    <span class="tabular-nums"> {{ variant.width }} × {{ variant.height }}</span>
                                </figcaption>
                            </UiCard>
                        </li>
                    </ul>
                </template>
            </div>
        </section>
    </div>
</template>
