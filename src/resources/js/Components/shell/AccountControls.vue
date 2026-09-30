<script setup lang="ts">
import UiButton from '@/Components/ui/UiButton.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { login, logout } from '@/routes';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Top-bar account area: sign in for guests; the username and sign out once signed in. The profile
// menu (avatar, settings) replaces the plain name when those pages exist (P1-03, P1-05).
const { auth, url } = usePageProps();
const user = computed(() => auth.value?.user ?? null);
const onLoginPage = computed(() => url.value.split(/[?#]/)[0] === login().url);
const signingOut = ref(false);

function signOut() {
    router.post(logout().url, {}, { onStart: () => (signingOut.value = true), onFinish: () => (signingOut.value = false) });
}
</script>

<template>
    <div v-if="user" class="flex items-center gap-2">
        <span class="hidden max-w-40 truncate text-sm font-medium text-fg-secondary sm:inline">{{ user.username }}</span>
        <UiButton variant="ghost" size="sm" :loading="signingOut" @click="signOut">Sign out</UiButton>
    </div>
    <UiButton v-else-if="!onLoginPage" variant="secondary" size="sm" :href="login().url">Sign in</UiButton>
</template>
