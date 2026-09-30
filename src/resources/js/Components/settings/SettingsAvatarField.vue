<script setup lang="ts">
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiProgress from '@/Components/ui/UiProgress.vue';
import { useUpload } from '@/Composables/useUpload';
import { destroy, update } from '@/routes/settings/profile/avatar';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// Avatar upload (FR-PROFILE-3): the file goes to storage through useUpload, and once it is ready
// the profile is pointed at it. The server checks ownership, collection and status again.
const props = defineProps<{
    name: string;
    avatar: App.Domain.Users.Data.AvatarData;
    rules: App.Domain.Media.Data.UploadCollectionData;
    error?: string;
}>();

const { phase, progress, error: uploadError, result, fileName, upload, retry, checkAgain, reset } = useUpload();
const fileInput = ref<HTMLInputElement | null>(null);
const saving = ref(false);
const removing = ref(false);

const busy = computed(() => ['uploading', 'processing', 'slow'].includes(phase.value) || saving.value || removing.value);
const megabytes = computed(() => `${Math.round((props.rules.maxBytes / 1024 / 1024) * 10) / 10} MB`);
// Stored but not usable yet (still processing) or refused by processing.
const storedProcessing = computed(() => props.avatar.status === 'processing' || props.avatar.status === 'uploaded');
const storedFailed = computed(() => props.avatar.status === 'failed' || props.avatar.status === 'quarantined');

function choose() {
    fileInput.value?.click();
}

function onFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) {
        upload(file, 'avatar');
    }
    // Picking the same file again starts a new upload.
    input.value = '';
}

watch(phase, (next) => {
    if (next !== 'ready' || !result.value) {
        return;
    }
    router.put(
        update().url,
        { media: result.value.mediaUlid },
        {
            preserveScroll: true,
            onStart: () => (saving.value = true),
            onFinish: () => {
                saving.value = false;
                reset();
            },
        },
    );
});

function remove() {
    router.delete(destroy().url, {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
    });
}
</script>

<template>
    <fieldset class="flex flex-col gap-4">
        <legend class="mb-2 text-body font-semibold text-fg">Avatar</legend>
        <div class="flex flex-wrap items-center gap-4">
            <UiAvatar :name="name" :src="avatar.url128" :size="96" />
            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap gap-2">
                    <input ref="fileInput" type="file" class="sr-only" tabindex="-1" aria-hidden="true" :accept="rules.accept" @change="onFile" />
                    <UiButton variant="secondary" size="sm" :loading="busy && !removing" :disabled="busy" @click="choose">
                        {{ avatar.status ? 'Change photo' : 'Upload photo' }}
                    </UiButton>
                    <UiButton v-if="avatar.status" variant="ghost" size="sm" :loading="removing" :disabled="busy" @click="remove">Remove</UiButton>
                </div>
                <p class="text-sm text-fg-secondary">{{ rules.typesLabel }}, up to {{ megabytes }}. Cropped to a square.</p>
            </div>
        </div>

        <div aria-live="polite" class="flex flex-col gap-2">
            <UiProgress v-if="phase === 'uploading'" :label="`Uploading ${fileName}`" :value="progress" />
            <UiProgress v-else-if="phase === 'processing' || saving" label="Preparing your photo" />
            <p v-else-if="phase === 'slow'" class="text-sm text-fg-secondary">
                This is taking longer than usual.
                <button type="button" class="font-medium text-brand underline-offset-4 hover:underline" @click="checkAgain">Check again</button>
            </p>
            <p v-else-if="phase === 'failed' || phase === 'unavailable'" role="alert" class="text-sm text-danger-fg">
                {{ uploadError }}
                <button v-if="phase === 'failed'" type="button" class="ml-1 font-medium text-brand underline-offset-4 hover:underline" @click="retry">
                    Try again
                </button>
            </p>
            <p v-else-if="error" role="alert" class="text-sm text-danger-fg">{{ error }}</p>
            <p v-else-if="storedProcessing" class="text-sm text-fg-secondary">
                Your new photo is still being prepared. It shows here once it is ready.
            </p>
            <p v-else-if="storedFailed" role="alert" class="text-sm text-danger-fg">That photo could not be used. Upload a different one.</p>
        </div>
    </fieldset>
</template>
