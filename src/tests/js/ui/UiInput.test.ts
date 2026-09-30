import UiInput from '@/Components/ui/UiInput.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('UiInput', () => {
    it('labels the input', () => {
        const wrapper = mount(UiInput, { props: { label: 'Username', id: 'u' } });
        expect(wrapper.get('label').attributes('for')).toBe('u');
        expect(wrapper.get('input').attributes('id')).toBe('u');
    });

    it('wires the error to aria-invalid and aria-describedby', () => {
        const wrapper = mount(UiInput, { props: { label: 'Username', id: 'u', error: 'Taken', hint: 'Public' } });
        const input = wrapper.get('input');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe('u-error u-hint');
        expect(wrapper.get('#u-error').text()).toBe('Taken');
    });

    it('focuses the input on mount when autofocus is set', () => {
        const wrapper = mount(UiInput, { props: { label: 'Email', id: 'e', autofocus: true }, attachTo: document.body });
        expect(document.activeElement).toBe(wrapper.get('input').element);
        wrapper.unmount();
    });

    it('does not take focus without autofocus', () => {
        const wrapper = mount(UiInput, { props: { label: 'Email', id: 'e' }, attachTo: document.body });
        expect(document.activeElement).not.toBe(wrapper.get('input').element);
        wrapper.unmount();
    });

    it('counts characters against maxlength', async () => {
        const wrapper = mount(UiInput, { props: { label: 'Bio', id: 'b', maxlength: 10, counter: true, modelValue: 'abc' } });
        expect(wrapper.get('#b-counter').text()).toBe('3/10');
    });
});

describe('UiInput attributes and affixes', () => {
    it('puts class on the wrapper and other attributes on the input only', () => {
        const wrapper = mount(UiInput, { props: { label: 'Name', id: 'n' }, attrs: { class: 'col-span-2', placeholder: 'Type' } });
        expect(wrapper.classes()).toContain('col-span-2');
        expect(wrapper.get('input').classes()).not.toContain('col-span-2');
        expect(wrapper.get('input').attributes('placeholder')).toBe('Type');
        expect(wrapper.attributes('placeholder')).toBeUndefined();
    });

    it('describes the input with its suffix so units are announced', () => {
        const wrapper = mount(UiInput, { props: { label: 'Width', id: 'w', suffix: 'px' } });
        expect(wrapper.get('input').attributes('aria-describedby')).toBe('w-suffix');
        expect(wrapper.get('#w-suffix').text()).toBe('px');
    });
});
