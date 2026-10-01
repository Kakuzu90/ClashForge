<script setup lang="ts">
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { headerLinks, visibleHeaderLinks } from '@/navigation';
import { login, logout } from '@/routes';
import { show as profilePage } from '@/routes/profile';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Top-bar account area: sign in for guests; the username and sign out once signed in, plus the
// Admin link for staff. The avatar and name open your own profile, which links on to settings; a
// fuller account menu can replace them once more settings pages exist (P1-05).
const { auth, can, url } = usePageProps();
const links = computed(() => visibleHeaderLinks(headerLinks, can.value));
const user = computed(() => auth.value?.user ?? null);
const onLoginPage = computed(() => url.value.split(/[?#]/)[0] === login().url);
const signingOut = ref(false);

function signOut() {
    router.post(logout().url, {}, { onStart: () => (signingOut.value = true), onFinish: () => (signingOut.value = false) });
}
</script>

<template>
    <div v-if="user" class="flex items-center gap-1 whitespace-nowrap sm:gap-2">
        <UiButton v-for="link in links" :key="link.key" variant="ghost" size="sm" :href="link.url">{{ link.label }}</UiButton>
        <Link
            :href="profilePage(user.username).url"
            class="flex min-h-11 items-center gap-2 rounded-md px-1 text-sm font-medium text-fg-secondary hover:text-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            :aria-label="`${user.username}, your profile`"
        >
            <UiAvatar :name="user.username" :src="user.avatarUrl" :size="32" />
            <span class="hidden max-w-40 truncate sm:inline">{{ user.username }}</span>
        </Link>
        <UiButton variant="ghost" size="sm" :loading="signingOut" @click="signOut">Sign out</UiButton>
    </div>
    <UiButton v-else-if="!onLoginPage" variant="secondary" size="sm" :href="login().url">Sign in</UiButton>
</template>
