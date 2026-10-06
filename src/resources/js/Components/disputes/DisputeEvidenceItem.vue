<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import UiProgress from '@/Components/ui/UiProgress.vue';
import { useUpload } from '@/Composables/useUpload';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// One picked image on its way to private evidence storage (specs/10 §3). The upload is usable once
// storage accepted it, while it is still being processed: the server attaches `processing` media too.
const props = defineProps<{ file: File; collection: App.Domain.Media.Enums.MediaCollection }>();
const emit = defineEmits<{ ready: [ulid: string]; failed: []; remove: [] }>();

const { phase, progress, error, result, upload, retry, checkAgain } = useUpload();
const preview = ref<string | null>(null);

onMounted(() => {
    preview.value = URL.createObjectURL(props.file);
    upload(props.file, props.collection);
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

watch(phase, (next) => {
    if (['processing', 'slow', 'ready'].includes(next) && result.value) {
        emit('ready', result.value.mediaUlid);
    } else if (next === 'failed' || next === 'unavailable') {
        emit('failed');
    }
});
</script>

<template>
    <li class="bg-surface-raised flex items-start gap-3 rounded-md border border-line p-2">
        <img v-if="preview" :src="preview" alt="" class="size-16 shrink-0 rounded-sm object-cover" width="64" height="64" />
        <div class="flex min-w-0 flex-1 flex-col gap-1" aria-live="polite">
            <p class="truncate text-sm font-medium text-fg">{{ file.name }}</p>
            <UiProgress v-if="phase === 'uploading'" :label="`Uploading ${file.name}`" :value="progress" hide-label />
            <p v-else-if="phase === 'processing' || phase === 'ready' || phase === 'slow'" class="text-sm text-fg-secondary">Uploaded</p>
            <p v-else-if="phase === 'failed' || phase === 'unavailable'" role="alert" class="text-sm text-danger-fg">
                {{ error }}
                <button v-if="phase === 'failed'" type="button" class="ml-1 font-medium text-brand underline-offset-4 hover:underline" @click="retry">
                    Try again
                </button>
            </p>
            <button
                v-if="phase === 'slow'"
                type="button"
                class="self-start text-sm font-medium text-brand underline-offset-4 hover:underline"
                @click="checkAgain"
            >
                Check again
            </button>
        </div>
        <UiButton size="sm" variant="ghost" :aria-label="`Remove ${file.name}`" @click="emit('remove')">Remove</UiButton>
    </li>
</template>
