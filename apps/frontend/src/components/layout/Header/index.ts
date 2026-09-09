/**
 * Шапка сайта по макету. Собирается из трёх полос, каждая — отдельный файл.
 * Замеры и решения: market-docs/13-header.md.
 */
export { default as Header } from './Header';
export { default as HeaderConnected } from './HeaderConnected';

export { default as TopBar } from './bars/TopBar';
export { default as MainBar } from './bars/MainBar';
export { default as CategoryBar } from './bars/CategoryBar';
export type { HeaderCategory } from './bars/CategoryBar';

export { default as UserActions } from './menu/UserActions';
export { default as CounterBadge } from './menu/CounterBadge';

export { default as HeaderMenu } from './menu/HeaderMenu';
export { default as MenuPromo } from './menu/MenuPromo';
export type { PromoProduct } from './menu/MenuPromo';
export type { MenuLink, MenuGroup, MenuSection } from './lib/menu-types';

export { ROOMS_SECTIONS } from './lib/rooms';
