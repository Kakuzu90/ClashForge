import AdminDiffViewer, { diffRecords } from '@/Components/admin/AdminDiffViewer.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('diffRecords', () => {
    it('marks added, removed, changed and unchanged keys in first-seen order', () => {
        expect(diffRecords({ role: 'user', note: 'same', gone: true }, { role: 'admin', note: 'same', fresh: [1] })).toEqual([
            { key: 'role', kind: 'changed', before: 'user', after: 'admin' },
            { key: 'note', kind: 'unchanged', before: 'same', after: 'same' },
            { key: 'gone', kind: 'removed', before: 'true', after: null },
            { key: 'fresh', kind: 'added', before: null, after: '[1]' },
        ]);
    });

    it('treats a missing side as empty', () => {
        expect(diffRecords(null, { role: 'user' })).toEqual([{ key: 'role', kind: 'added', before: null, after: 'user' }]);
        expect(diffRecords(null, null)).toEqual([]);
    });

    it('compares nested values as JSON', () => {
        expect(diffRecords({ a: { b: 1 } }, { a: { b: 1 } })[0]?.kind).toBe('unchanged');
        expect(diffRecords({ a: { b: 1 } }, { a: { b: 2 } })[0]?.kind).toBe('changed');
    });
});

describe('AdminDiffViewer', () => {
    it('spells out each change in words', () => {
        const wrapper = mount(AdminDiffViewer, { props: { before: { role: 'user' }, after: { role: 'admin', note: 'x' } } });
        const rows = wrapper.findAll('tbody tr').map((row) => row.findAll('th, td').map((cell) => cell.text()));

        expect(rows).toEqual([
            ['role', 'user', 'admin', 'Changed'],
            ['note', 'None', 'x', 'Added'],
        ]);
    });

    it('renders stored markup as text, never as HTML', () => {
        const payload = '<img src=x onerror=alert(1)>';
        const wrapper = mount(AdminDiffViewer, { props: { before: { note: payload }, after: null } });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain(payload);
    });

    it('says so when nothing was recorded', () => {
        expect(mount(AdminDiffViewer, { props: { before: null, after: null } }).text()).toBe('No before or after values recorded.');
    });
});
