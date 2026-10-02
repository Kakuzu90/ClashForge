<script setup lang="ts">
import UiAvatar from '@/Components/ui/UiAvatar.vue';
import UiButton from '@/Components/ui/UiButton.vue';
import UiDropdownMenu, { type MenuItem } from '@/Components/ui/UiDropdownMenu.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { login, logout } from '@/routes';
import { show as profilePage } from '@/routes/profile';
import { edit as profileSettings } from '@/routes/settings/profile';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// Top-bar account area: sign in for guests; once signed in, the avatar opens the account menu
// (specs/18 §6: your profile, settings, sign out). Staff links sit in SiteHeader.
const { auth, url } = usePageProps();
const user = computed(() => auth.value?.user ?? null);
const onLoginPage = computed(() => url.value.split(/[?#]/)[0] === login().url);
const signingOut = ref(false);
const menuItems = computed<MenuItem[]>(() =>
    user.value
        ? [
              { key: 'profile', label: 'Your profile', href: profilePage(user.value.username).url },
              { key: 'settings', label: 'Settings', href: profileSettings().url },
              { key: 'sign-out', label: signingOut.value ? 'Signing out…' : 'Sign out' },
          ]
        : [],
);

function signOut() {
    router.post(logout().url, {}, { onStart: () => (signingOut.value = true), onFinish: () => (signingOut.value = false) });
}

function onSelect(key: string) {
    if (key === 'sign-out') {
        signOut();
    }
}
</script>

<template>
    <div v-if="user" class="flex items-center gap-1 whitespace-nowrap sm:gap-2">
        <UiDropdownMenu :items="menuItems" :label="`${user.username}, account menu`" @select="onSelect">
            <template #trigger>
                <UiAvatar :name="user.username" :src="user.avatarUrl" :size="32" />
                <span class="hidden max-w-40 truncate sm:inline">{{ user.username }}</span>
                <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" class="hidden sm:block">
                    <path d="M3 4.5l3 3 3-3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </template>
        </UiDropdownMenu>
    </div>
    <UiButton v-else-if="!onLoginPage" variant="secondary" size="sm" :href="login().url">Sign in</UiButton>
</template>
