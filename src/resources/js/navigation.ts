import { home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as profileSettings } from '@/routes/settings/profile';

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

export interface HeaderLink {
    key: string;
    label: string;
    href: () => string;
    /** Shared `auth.can` ability required to see the link. */
    can: string;
}

// Account-area links in the top bar, next to the account controls.
export const headerLinks: HeaderLink[] = [{ key: 'admin', label: 'Admin', href: () => adminDashboard().url, can: 'accessAdmin' }];

export function visibleHeaderLinks(links: HeaderLink[], can: Record<string, boolean>): (HeaderLink & { url: string })[] {
    return links.filter((link) => can[link.can] === true).map((link) => ({ ...link, url: link.href() }));
}

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

export interface SettingsLink {
    key: string;
    label: string;
    href: () => string;
}

// specs/18 §6 Settings sub-nav: Profile · Privacy · Accounts · Security · Notifications · Danger zone.
// Each section joins when its page ships (P1-04, P1-05, P2).
export const settingsNav: SettingsLink[] = [{ key: 'profile', label: 'Profile', href: () => profileSettings().url }];
