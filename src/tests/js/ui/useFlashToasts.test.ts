import { useFlashToasts } from '@/Composables/useFlashToasts';
import { useToast } from '@/Composables/useToast';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick, reactive } from 'vue';

const page = reactive<{ props: { flash: { success: string | null; error: string | null } } }>({
    props: { flash: { success: null, error: null } },
});

vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

const Host = defineComponent({
    setup: () => useFlashToasts(),
    template: '<div />',
});

function visit(flash: { success?: string | null; error?: string | null }) {
    page.props = { flash: { success: null, error: null, ...flash } };
}

afterEach(() => useToast().clear());

describe('useFlashToasts', () => {
    it('shows the flash of the page it mounts on', () => {
        visit({ success: 'Profile saved.' });
        mount(Host);

        expect(useToast().toasts.value.map((t) => [t.kind, t.title])).toEqual([['success', 'Profile saved.']]);
    });

    it('shows a success and an error from later visits, and the same message again after a second save', async () => {
        visit({});
        mount(Host);

        visit({ success: 'Privacy settings saved.' });
        await nextTick();
        visit({ success: 'Privacy settings saved.' });
        await nextTick();
        visit({ error: 'Too many changes. Wait a minute and try again.' });
        await nextTick();

        expect(useToast().toasts.value.map((t) => [t.kind, t.title])).toEqual([
            ['success', 'Privacy settings saved.'],
            ['success', 'Privacy settings saved.'],
            ['danger', 'Too many changes. Wait a minute and try again.'],
        ]);
    });

    it('shows a message once when a second toaster mounts on the same page', () => {
        visit({ success: 'Avatar updated.' });
        mount(Host);
        mount(Host);

        expect(useToast().toasts.value).toHaveLength(1);
    });

    it('shows nothing for a visit without a message', async () => {
        visit({ success: 'x' });
        mount(Host);
        useToast().clear();

        visit({});
        await nextTick();

        expect(useToast().toasts.value).toHaveLength(0);
    });
});
