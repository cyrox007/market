import type { Category } from '../../../../lib/api';
import type { MenuGroup, MenuLink, MenuSection } from './menu-types';

/** Ссылка на раздел; его дети идут четвёртым уровнем под ней */
const toLink = (prefix: string, item: Category): MenuLink => ({
  to: `${prefix}/${item.slug}`,
  label: item.name,
  children: (item.children ?? []).map((child) => ({
    to: `${prefix}/${child.slug}`,
    label: child.name,
  })),
});

/**
 * Дерево таксономии → разделы выпадающего меню, одинаково для каталога и комнат.
 * Корневой раздел всегда ссылка на себя: раскрытие даёт подразделы, клик — саму страницу.
 * Подраздел с детьми становится колонкой с заголовком, листья собираются в колонку без него.
 */
export function toMenuSections(items: Category[], prefix: string): MenuSection[] {
  return items.map((item) => {
    const children = item.children ?? [];
    const to = `${prefix}/${item.slug}`;
    if (!children.length) {
      return { id: item.slug, label: item.name, to };
    }

    const groups: MenuGroup[] = children
      .filter((child) => (child.children ?? []).length)
      .map((child) => ({
        title: child.name,
        titleTo: `${prefix}/${child.slug}`,
        links: (child.children ?? []).map((grandchild) => toLink(prefix, grandchild)),
      }));

    const leaves = children.filter((child) => !(child.children ?? []).length);
    if (leaves.length) {
      groups.push({ links: leaves.map((leaf) => toLink(prefix, leaf)) });
    }

    return { id: item.slug, label: item.name, to, groups };
  });
}
