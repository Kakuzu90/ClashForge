import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({ Head: { render: () => null } }));

import Index from '@/Pages/Home/Index.vue';

describe('Home/Index', () => {
    it('renders the product name as the only h1', () => {
        const wrapper = mount(Index);
        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.get('h1').text()).toBe('Clash Commons');
    });
});
