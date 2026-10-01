import SettingsAvatarField from '@/Components/settings/SettingsAvatarField.vue';
import { settingsNav } from '@/navigation';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';

const put = vi.fn();
const del = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    router: { put: (...args: unknown[]) => put(...args), delete: (...args: unknown[]) => del(...args) },
    Link: { template: '<a><slot /></a>' },
}));

const phase = ref('idle');
const result = ref<{ mediaUlid: string } | null>(null);
const upload = vi.fn();
vi.mock('@/Composables/useUpload', () => ({
    useUpload: () => ({
        phase,
        progress: ref(0),
        error: ref(null),
        result,
        fileName: ref('me.png'),
        upload,
        retry: vi.fn(),
        checkAgain: vi.fn(),
        reset: vi.fn(),
    }),
}));

// The upload refs are shared by every mount, so each test unmounts its field.
enableAutoUnmount(afterEach);

const rules = {
    value: 'avatar',
    label: 'Avatar',
    maxBytes: 2 * 1024 * 1024,
    accept: 'image/jpeg,image/png,image/webp',
    typesLabel: 'JPEG, PNG or WebP',
};
const noAvatar = { status: null, url512: null, url128: null, url48: null };

function mountField(avatar = noAvatar) {
    return mount(SettingsAvatarField, { props: { name: 'Chief', avatar, rules } as never });
}

describe('SettingsAvatarField', () => {
    beforeEach(() => {
        phase.value = 'idle';
        result.value = null;
        put.mockClear();
        del.mockClear();
    });

    it('states the upload rules from the server', () => {
        expect(mountField().text()).toContain('JPEG, PNG or WebP, up to 2 MB. Cropped to a square.');
    });

    it('points the profile at the upload once it is ready', async () => {
        mountField();
        result.value = { mediaUlid: '01j0000000000000000000000a' };
        phase.value = 'ready';
        await nextTick();

        expect(put).toHaveBeenCalledOnce();
        expect(put.mock.calls[0]?.[1]).toEqual({ media: '01j0000000000000000000000a' });
    });

    it('offers Remove only when there is an avatar', async () => {
        expect(mountField().text()).not.toContain('Remove');

        const withAvatar = mountField({ status: 'ready', url512: 'a', url128: 'b', url48: 'c' } as never);
        const remove = withAvatar.findAll('button').find((b) => b.text() === 'Remove');
        await remove?.trigger('click');

        expect(del).toHaveBeenCalledOnce();
    });

    it('explains an avatar that processing refused', () => {
        expect(mountField({ status: 'quarantined', url512: null, url128: null, url48: null } as never).text()).toContain(
            'That photo could not be used.',
        );
    });
});

describe('settings nav', () => {
    it('lists Profile then Privacy', () => {
        expect(settingsNav.map((link) => link.key)).toEqual(['profile', 'privacy']);
    });
});
