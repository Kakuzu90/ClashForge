import AdminTable from '@/Components/admin/AdminTable.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { h } from 'vue';

interface Row {
    id: number;
    name: string;
}

const columns = [
    { key: 'name', label: 'Name', class: 'w-48' },
    { key: 'id', label: 'Id' },
];
const rows: Row[] = [
    { id: 1, name: 'First' },
    { id: 2, name: 'Second' },
];

function table(props: Record<string, unknown> = {}, slots: Record<string, unknown> = {}) {
    return mount(AdminTable, {
        props: { columns, rows, rowKey: (row: Row) => row.id, caption: 'Sample table', ...props },
        slots,
    } as never);
}

describe('AdminTable', () => {
    it('renders a captioned table with the cell values', () => {
        const wrapper = table();

        expect(wrapper.find('caption').text()).toBe('Sample table');
        expect(wrapper.attributes('aria-label')).toBe('Sample table');
        expect(wrapper.findAll('tbody tr').map((row) => row.text())).toEqual(['First1', 'Second2']);
    });

    it('keeps the column classes on skeleton rows while loading', () => {
        const wrapper = table({ loading: true, skeletonRows: 3 });
        const body = wrapper.findAll('tbody tr');

        expect(body).toHaveLength(3);
        expect(body[0]?.findAll('td')[0]?.classes()).toContain('w-48');
        expect(wrapper.text()).not.toContain('First');
    });

    it('shows the empty slot when there are no rows', () => {
        const wrapper = table({ rows: [] }, { empty: () => 'Nothing matches.' });

        expect(wrapper.find('tbody td').attributes('colspan')).toBe('2');
        expect(wrapper.text()).toContain('Nothing matches.');
    });

    it('opens and closes a detail row from its toggle', async () => {
        const wrapper = table(
            { expandable: true, expandLabel: (row: Row) => `Details for ${row.name}` },
            { detail: ({ row }: { row: Row }) => h('p', `Detail of ${row.name}`) },
        );
        const toggle = wrapper.get('button[aria-label="Details for First"]');
        const detail = wrapper.get(`#${toggle.attributes('aria-controls')}`);

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(detail.isVisible()).toBe(false);

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(detail.text()).toBe('Detail of First');
        expect(wrapper.text()).not.toContain('Detail of Second');

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });
});
