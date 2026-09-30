import UiModal from '@/Components/ui/UiModal.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, nextTick, ref } from 'vue';

const Host = defineComponent({
    components: { UiModal },
    setup: () => ({ open: ref(false) }),
    template: `
        <button id="trigger" @click="open = true">Open</button>
        <UiModal v-model:open="open" title="Dialog">
            <button id="first">First</button>
            <button id="last">Last</button>
        </UiModal>
    `,
});

async function openModal() {
    const wrapper = mount(Host, { attachTo: document.body });
    const trigger = document.getElementById('trigger') as HTMLButtonElement;
    trigger.focus();
    trigger.click();
    await nextTick();
    await nextTick();
    return { wrapper, trigger };
}

describe('UiModal', () => {
    it('is a labelled modal dialog and focuses its first control', async () => {
        const { wrapper } = await openModal();
        const dialog = document.querySelector('[role="dialog"]') as HTMLElement;
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(document.getElementById(dialog.getAttribute('aria-labelledby')!)?.textContent).toBe('Dialog');
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Close');
        wrapper.unmount();
    });

    it('wraps Tab from the last control back to the first', async () => {
        const { wrapper } = await openModal();
        (document.getElementById('last') as HTMLElement).focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));
        expect(document.activeElement?.getAttribute('aria-label')).toBe('Close');
        wrapper.unmount();
    });

    it('closes on Escape and returns focus to the trigger', async () => {
        const { wrapper, trigger } = await openModal();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await nextTick();
        await nextTick();
        expect(document.querySelector('[role="dialog"]')).toBeNull();
        expect(document.activeElement).toBe(trigger);
        wrapper.unmount();
    });
});
