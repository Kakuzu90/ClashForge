import UiCheckbox from '@/Components/ui/UiCheckbox.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiCheckbox', () => {
    it('labels the input and updates the model', async () => {
        const wrapper = mount(UiCheckbox, { props: { label: 'Keep me signed in', modelValue: false } });
        const input = wrapper.get('input[type="checkbox"]');

        expect(wrapper.get('label').attributes('for')).toBe(input.attributes('id'));
        await input.setValue(true);
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true]);
    });

    it('links the error to the input and marks it invalid', () => {
        const wrapper = mount(UiCheckbox, { props: { label: 'Terms', error: 'Tick this to continue.' } });
        const input = wrapper.get('input');
        const describedBy = input.attributes('aria-describedby') ?? '';

        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.get(`#${describedBy.split(' ')[0]}`).text()).toBe('Tick this to continue.');
    });

    it('sets the indeterminate property', () => {
        const wrapper = mount(UiCheckbox, { props: { label: 'Some', indeterminate: true } });

        expect((wrapper.get('input').element as HTMLInputElement).indeterminate).toBe(true);
    });
});
