import { isActive, primaryNav, visibleNavItems, type NavItem } from '@/navigation';
import { describe, expect, it } from 'vitest';

const items: NavItem[] = [
    { key: 'home', label: 'Home', icon: 'home', href: () => '/' },
    { key: 'bases', label: 'Bases', icon: 'bases' },
    { key: 'admin', label: 'Admin', icon: 'profile', href: () => '/admin', can: 'access-admin' },
];

describe('navigation', () => {
    it('shows only items whose page exists', () => {
        expect(visibleNavItems(items, {}).map((i) => i.key)).toEqual(['home']);
    });

    it('requires the shared ability when the item declares one', () => {
        expect(visibleNavItems(items, { 'access-admin': true }).map((i) => i.key)).toEqual(['home', 'admin']);
        expect(visibleNavItems(items, { 'access-admin': false }).map((i) => i.key)).toEqual(['home']);
    });

    it('puts Search in the bottom tabs only: the top bar has its own search link (P3-05)', () => {
        const search = visibleNavItems(primaryNav, {}).find((item) => item.key === 'search');

        expect(search?.url).toBe('/search');
        expect(search?.tabOnly).toBe(true);
    });

    it('matches home exactly and sections by prefix', () => {
        expect(isActive('/', '/')).toBe(true);
        expect(isActive('/bases', '/')).toBe(false);
        expect(isActive('/bases/th17?sort=new', '/bases')).toBe(true);
        expect(isActive('/basesx', '/bases')).toBe(false);
    });
});
