<script setup lang="ts">
import UiAlert from '@/Components/ui/UiAlert.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { onMounted, ref, watch } from 'vue';

// The site banner while the Clash of Clans API is unavailable (specs/09 §7, specs/23 §5). Closing
// it hides it for the rest of this tab's session; the flag clears once the API is back, so the next
// outage shows it again (owner decision 2026-10-02, P2-10).
const KEY = 'coc-api-banner-dismissed';

const { cocApi } = usePageProps();
const dismissed = ref(false);

function read(): boolean {
    try {
        return window.sessionStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
}

function write(value: boolean): void {
    try {
        if (value) {
            window.sessionStorage.setItem(KEY, '1');
        } else {
            window.sessionStorage.removeItem(KEY);
        }
    } catch {
        // Storage blocked: the banner simply comes back on the next page.
    }
}

function dismiss(): void {
    dismissed.value = true;
    write(true);
}

onMounted(() => {
    // A page loaded after the outage ended clears the old dismissal, so the next outage shows.
    if (cocApi.value === null) {
        write(false);
    }
    dismissed.value = cocApi.value !== null && read();
    watch(cocApi, (notice) => {
        if (notice === null) {
            dismissed.value = false;
            write(false);
        }
    });
});
</script>

<template>
    <UiAlert
        v-if="cocApi && !dismissed"
        kind="maintenance"
        :title="cocApi.reason === 'maintenance' ? 'Clash of Clans is down for maintenance' : 'Clash of Clans is not answering right now'"
        dismissible
        @dismiss="dismiss"
    >
        Game data may be a little out of date, and verifying accounts is paused until it is back.
    </UiAlert>
</template>
