/**
 * Шапка сайта по макету. Собирается из трёх полос, каждая — отдельный файл.
 * Замеры и решения: market-docs/13-header.md.
 */
export { default as Header } from './Header';

export { default as TopBar } from './TopBar';
export { default as MainBar } from './MainBar';
export { default as CategoryBar } from './CategoryBar';
export type { HeaderCategory } from './CategoryBar';

export { default as UserActions } from './UserActions';
export { default as CounterBadge } from './CounterBadge';

export { default as HeaderMenu } from './HeaderMenu';
export { default as MenuPromo } from './MenuPromo';
export type { PromoProduct } from './MenuPromo';
export type { MenuLink, MenuGroup, MenuSection } from './menu-types';

export { HEADER_CONTAINER } from './container';
