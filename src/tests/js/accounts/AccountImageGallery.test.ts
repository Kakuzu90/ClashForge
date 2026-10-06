import AccountImageGallery from '@/Components/accounts/AccountImageGallery.vue';
import UploadQueueItem from '@/Components/uploads/UploadQueueItem.vue';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const router = vi.hoisted(() => ({ post: vi.fn(), delete: vi.fn(), reload: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router, Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } }));

// The upload itself is the queue item's business; here it reports storage accepted the file.
vi.mock('@/Components/uploads/UploadQueueItem.vue', () => ({
    default: {
        props: ['file', 'collection', 'refused'],
        emits: ['ready', 'failed', 'remove'],
        template: '<li class="queued"><span>{{ file.name }}</span><span v-if="refused" role="alert">{{ refused }}</span></li>',
    },
}));

type Image = App.Domain.PlayerAccounts.Data.AccountImageData;

const variant = (name: App.Domain.Media.Enums.VariantName, n: number) => ({
    name,
    url: `https://media.test/${n}/${name}.webp`,
    width: 800,
    height: 600,
});
const image = (n: number, overrides: Partial<Image> = {}): Image => ({
    ulid: `01J00000000000000000000IM${n}`,
    card: variant('card', n),
    full: variant('full', n),
    processing: false,
    failed: false,
    ...overrides,
});
const upload: App.Domain.Media.Data.UploadCollectionData = {
    value: 'account_image',
    label: 'Account image',
    maxBytes: 5 * 1024 * 1024,
    accept: 'image/jpeg,image/png,image/webp',
    typesLabel: 'JPEG, PNG or WebP',
};

const render = (images: Image[], canManage = false) =>
    mount(AccountImageGallery, {
        props: {
            accountUlid: '01J0000000000000000000CARD',
            accountName: 'Fixture Chief',
            images,
            canManage,
            max: 5,
            upload: canManage ? upload : null,
        },
        attachTo: document.body,
    });

const viewerImage = () => document.querySelector('[role="dialog"] img') as HTMLImageElement | null;

afterEach(() => {
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('AccountImageGallery', () => {
    it('renders nothing for a visitor when there are no ready images', () => {
        expect(render([]).find('section').exists()).toBe(false);
        expect(
            render([image(1, { card: null, full: null, processing: true })])
                .find('section')
                .exists(),
        ).toBe(false);
    });

    it('shows a visitor the ready images with intrinsic sizes and no owner controls', () => {
        const wrapper = render([image(1), image(2)]);

        const imgs = wrapper.findAll('img');
        expect(imgs).toHaveLength(2);
        expect(imgs[0]!.attributes('width')).toBe('800');
        expect(imgs[0]!.attributes('alt')).toBe('Fixture Chief, image 1 of 2');
        expect(wrapper.text()).not.toContain('Remove');
        expect(wrapper.text()).not.toContain('Add images');
    });

    it('opens the viewer and steps with buttons and arrow keys, wrapping round', async () => {
        const wrapper = render([image(1), image(2), image(3)]);

        await wrapper.findAll('button')[1]!.trigger('click');
        await nextTick();
        expect(viewerImage()?.src).toContain('/2/full.webp');

        const next = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Next',
        ) as HTMLButtonElement;
        next.click();
        await nextTick();
        expect(viewerImage()?.src).toContain('/3/full.webp');

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        await nextTick();
        expect(viewerImage()?.src).toContain('/1/full.webp');

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));
        await nextTick();
        expect(viewerImage()?.src).toContain('/3/full.webp');
        wrapper.unmount();
    });

    it('gives the owner the counter, remove buttons, processing and failed tiles, and the upload area', () => {
        const wrapper = render(
            [image(1), image(2, { card: null, full: null, processing: true }), image(3, { card: null, full: null, failed: true })],
            true,
        );

        expect(wrapper.text()).toContain('3 of 5 images');
        expect(wrapper.findAll('button').filter((b) => b.text() === 'Remove')).toHaveLength(3);
        expect(wrapper.text()).toContain('Processing image');
        expect(wrapper.text()).toContain('This image could not be used.');
        expect(wrapper.text()).toContain('Add images');
        expect(wrapper.text()).toContain('up to 5 MB each');
    });

    it('hides the upload area once the account has five images', () => {
        const wrapper = render(
            [1, 2, 3, 4, 5].map((n) => image(n)),
            true,
        );

        expect(wrapper.text()).not.toContain('Add images');
        expect(wrapper.text()).toContain('This account has the most images it can have.');
    });

    it('asks before removing, then sends the delete', async () => {
        const wrapper = render([image(1)], true);

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Remove')!
            .trigger('click');
        await nextTick();
        expect(router.delete).not.toHaveBeenCalled();

        const confirm = Array.from(document.querySelectorAll('[role="dialog"] button')).find(
            (b) => b.textContent?.trim() === 'Remove image',
        ) as HTMLButtonElement;
        confirm.click();
        expect(router.delete).toHaveBeenCalledWith('/accounts/01J0000000000000000000CARD/images/01J00000000000000000000IM1', expect.any(Object));
        wrapper.unmount();
    });

    it('attaches each picked file once, async, even when ready fires again', async () => {
        const wrapper = render([], true);
        const input = wrapper.get('input[type="file"]');
        Object.defineProperty(input.element, 'files', { value: [new File(['a'], 'a.png'), new File(['b'], 'b.png')] });
        await input.trigger('change');

        const items = wrapper.findAllComponents(UploadQueueItem);
        expect(items).toHaveLength(2);
        items[0]!.vm.$emit('ready', '01J00000000000000000000UP1');
        items[0]!.vm.$emit('ready', '01J00000000000000000000UP1');
        items[1]!.vm.$emit('ready', '01J00000000000000000000UP2');

        expect(router.post).toHaveBeenCalledTimes(2);
        expect(router.post).toHaveBeenCalledWith(
            '/accounts/01J0000000000000000000CARD/images',
            { media: '01J00000000000000000000UP1' },
            expect.objectContaining({ async: true }),
        );
    });

    it('shows the refusal on the queued file', async () => {
        router.post.mockImplementationOnce((_url: string, _data: unknown, options: { onError: (errors: Record<string, string>) => void }) =>
            options.onError({ media: 'This image is already on this account.' }),
        );
        const wrapper = render([], true);
        const input = wrapper.get('input[type="file"]');
        Object.defineProperty(input.element, 'files', { value: [new File(['a'], 'a.png')] });
        await input.trigger('change');

        wrapper.findComponent(UploadQueueItem).vm.$emit('ready', '01J00000000000000000000UP1');
        await nextTick();

        expect(wrapper.get('[role="alert"]').text()).toBe('This image is already on this account.');
    });

    it('reloads the account while an image is processing', () => {
        vi.useFakeTimers();
        render([image(1, { card: null, full: null, processing: true })], true);

        vi.advanceTimersByTime(4000);
        expect(router.reload).toHaveBeenCalledWith({ only: ['account'], async: true });
        vi.useRealTimers();
    });
});
