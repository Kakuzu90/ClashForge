import UiSelect from '@/Components/ui/UiSelect.vue';
import { mount } from '@vue/test-utils';
import { beforeAll, describe, expect, it, vi } from 'vitest';

// jsdom has no layout, so no scrollIntoView.
beforeAll(() => {
    Element.prototype.scrollIntoView = vi.fn();
});

const options = [
    { value: 'avatar', label: 'Avatar' },
    { value: 'account_image', label: 'Account image' },
    { value: 'base_screenshot', label: 'Base screenshot', hint: 'War and farming bases' },
];

function searchable(modelValue: string | null = null) {
    return mount(UiSelect, {
        props: { label: 'Collection', options, searchable: true, modelValue, 'onUpdate:modelValue': () => {} },
        attachTo: document.body,
    });
}

describe('UiSelect', () => {
    it('renders a native select by default', () => {
        const wrapper = mount(UiSelect, { props: { label: 'Collection', options, modelValue: 'avatar' } });

        expect(wrapper.find('select').exists()).toBe(true);
        expect(wrapper.findAll('option')).toHaveLength(3);
        expect(wrapper.find('[role="combobox"]').exists()).toBe(false);
    });

    it('shows the selected label and a collapsed combobox', () => {
        const wrapper = searchable('account_image');
        const input = wrapper.get('[role="combobox"]');

        expect((input.element as HTMLInputElement).value).toBe('Account image');
        expect(input.attributes('aria-expanded')).toBe('false');
        expect(input.attributes('aria-controls')).toBe(wrapper.get('[role="listbox"]').attributes('id'));
    });

    it('filters by label and hint as the user types', async () => {
        const wrapper = searchable();
        const input = wrapper.get('[role="combobox"]');

        await input.setValue('war');
        expect(wrapper.findAll('[role="option"]').map((o) => o.text())).toEqual([expect.stringContaining('Base screenshot')]);

        await input.setValue('zzz');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(0);
        expect(wrapper.text()).toContain('No matches');
    });

    it('picks with the keyboard and moves the active descendant', async () => {
        const wrapper = searchable();
        const input = wrapper.get('[role="combobox"]');

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-expanded')).toBe('true');
        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-activedescendant')).toBe(wrapper.findAll('[role="option"]')[1].attributes('id'));

        await input.trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['account_image']);
        expect(input.attributes('aria-expanded')).toBe('false');
    });

    it('closes on Escape without changing the value', async () => {
        const wrapper = searchable('avatar');
        const input = wrapper.get('[role="combobox"]');

        await input.trigger('click');
        await input.setValue('base');
        await input.trigger('keydown', { key: 'Escape' });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect((input.element as HTMLInputElement).value).toBe('Avatar');
    });

    it('selects on pointer and marks the chosen option', async () => {
        const wrapper = searchable();
        await wrapper.get('[role="combobox"]').trigger('click');
        await wrapper.findAll('[role="option"]')[2].trigger('mousedown');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['base_screenshot']);

        await wrapper.setProps({ modelValue: 'base_screenshot' });
        await wrapper.get('[role="combobox"]').trigger('click');
        expect(wrapper.findAll('[role="option"]')[2].attributes('aria-selected')).toBe('true');
    });

    it('links the error to the field', () => {
        const wrapper = mount(UiSelect, { props: { label: 'Collection', options, searchable: true, error: 'Pick one.' } });
        const input = wrapper.get('[role="combobox"]');

        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.get(`#${input.attributes('aria-describedby')}`).text()).toBe('Pick one.');
    });
});
