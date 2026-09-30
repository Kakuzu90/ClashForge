import { home } from '@/routes';

export type NavIconName = 'home' | 'bases' | 'recruit' | 'search' | 'market' | 'profile';

export interface NavItem {
    key: string;
    label: string;
    icon: NavIconName;
    /** Wayfinder URL. Items without one have no page yet and are not shown (owner decision, P0-04). */
    href?: () => string;
    /** Shared `auth.can` ability required to see the item (specs/04: never `role`). */
    can?: string;
}

// specs/18 §5: Home · Bases · Recruit · Market · Profile. Market's slot is Search until Phase 6.
// When a page ships, its task adds the Wayfinder import and `href` here.
export const primaryNav: NavItem[] = [
    { key: 'home', label: 'Home', icon: 'home', href: () => home().url },
    { key: 'bases', label: 'Bases', icon: 'bases' },
    { key: 'recruit', label: 'Recruit', icon: 'recruit' },
    { key: 'search', label: 'Search', icon: 'search' },
    { key: 'profile', label: 'Profile', icon: 'profile' },
];

export interface ResolvedNavItem extends NavItem {
    url: string;
}

export function visibleNavItems(items: NavItem[], can: Record<string, boolean>): ResolvedNavItem[] {
    return items.filter((item) => item.href && (!item.can || can[item.can] === true)).map((item) => ({ ...item, url: item.href!() }));
}

export function isActive(currentUrl: string, itemUrl: string): boolean {
    const path = currentUrl.split(/[?#]/)[0] || '/';
    return itemUrl === '/' ? path === '/' : path === itemUrl || path.startsWith(`${itemUrl}/`);
}
