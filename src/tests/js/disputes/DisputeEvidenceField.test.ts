import DisputeEvidenceField from '@/Components/disputes/DisputeEvidenceField.vue';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';

// Every picked file gets its own upload; each mock run keeps its own refs so a test can move one
// upload along without touching the others.
const runs = vi.hoisted(
    () =>
        [] as {
            phase: { value: string };
            result: { value: { mediaUlid: string } | null };
            upload: ReturnType<typeof vi.fn>;
            retry: ReturnType<typeof vi.fn>;
        }[],
);
vi.mock('@/Composables/useUpload', () => ({
    useUpload: () => {
        const run = { phase: ref('idle'), result: ref<{ mediaUlid: string } | null>(null), upload: vi.fn(), retry: vi.fn() };
        runs.push(run);
        return { ...run, progress: ref(0), error: ref('Upload failed.'), fileName: ref(null), checkAgain: vi.fn(), reset: vi.fn() };
    },
}));

enableAutoUnmount(afterEach);

const upload = { value: 'evidence', label: 'Report evidence', maxBytes: 5 * 1024 * 1024, accept: 'image/jpeg,image/png', typesLabel: 'JPEG or PNG' };

function mountField(max = 3) {
    return mount(DisputeEvidenceField, {
        props: { label: 'Images', hint: 'Screenshots.', max, upload, modelValue: [], busy: false } as never,
    });
}

function pick(wrapper: ReturnType<typeof mountField>, names: string[]) {
    const input = wrapper.find('input[type="file"]');
    const files = names.map((name) => new File(['x'], name, { type: 'image/png' }));
    Object.defineProperty(input.element, 'files', { value: files, configurable: true });
    return input.trigger('change');
}

async function finish(index: number, ulid: string) {
    runs[index].result.value = { mediaUlid: ulid };
    runs[index].phase.value = 'processing';
    await nextTick();
    await nextTick();
}

describe('DisputeEvidenceField', () => {
    beforeEach(() => {
        runs.length = 0;
        globalThis.URL.createObjectURL = vi.fn(() => 'blob:preview');
        globalThis.URL.revokeObjectURL = vi.fn();
    });

    it('starts an upload per picked image and reports busy until each is accepted', async () => {
        const wrapper = mountField();
        await pick(wrapper, ['a.png', 'b.png']);
        await flushPromises();

        expect(runs).toHaveLength(2);
        expect(runs[0].upload).toHaveBeenCalledWith(expect.any(File), 'evidence');
        expect(wrapper.emitted('update:busy')?.at(-1)).toEqual([true]);

        await finish(0, 'ULID-A');
        await finish(1, 'ULID-B');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['ULID-A', 'ULID-B']]);
        expect(wrapper.emitted('update:busy')?.at(-1)).toEqual([false]);
    });

    it('keeps to the cap the server gives and says what was left out', async () => {
        const wrapper = mountField(2);
        await pick(wrapper, ['a.png', 'b.png', 'c.png']);

        expect(runs).toHaveLength(2);
        expect(wrapper.text()).toContain('Only 2 more images fit');
        expect(wrapper.text()).toContain('That is the most images you can send.');
        expect(wrapper.find('input[type="file"]').attributes('accept')).toBe('image/jpeg,image/png');
    });

    it('drops a removed or failed image from the value', async () => {
        const wrapper = mountField();
        await pick(wrapper, ['a.png', 'b.png']);
        await finish(0, 'ULID-A');
        await finish(1, 'ULID-B');

        await wrapper
            .findAll('li button')
            .find((button) => button.text() === 'Remove')!
            .trigger('click');
        await nextTick();
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['ULID-B']]);

        runs[1].phase.value = 'failed';
        await nextTick();
        await nextTick();
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[]]);
        expect(wrapper.emitted('update:busy')?.at(-1)).toEqual([false]);
    });

    it('offers a retry on a failed upload and takes the image once it goes through', async () => {
        const wrapper = mountField();
        await pick(wrapper, ['a.png']);
        runs[0].phase.value = 'failed';
        await nextTick();
        await nextTick();

        expect(wrapper.find('[role="alert"]').text()).toContain('Upload failed.');
        await wrapper.findAll('button').find((button) => button.text() === 'Try again')!.trigger('click');
        expect(runs[0].retry).toHaveBeenCalledOnce();

        await finish(0, 'ULID-A');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['ULID-A']]);
        expect(wrapper.emitted('update:busy')?.at(-1)).toEqual([false]);
    });

    it('shows the server error for the field', () => {
        const wrapper = mount(DisputeEvidenceField, {
            props: { label: 'Images', hint: 'Screenshots.', max: 3, upload, error: 'Attach at most 3 images.' } as never,
        });

        expect(wrapper.find('[role="alert"]').text()).toBe('Attach at most 3 images.');
    });
});
