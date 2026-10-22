import { fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import Header from './Header';
import type { MenuSection } from './lib/menu-types';

const sections: MenuSection[] = [
  {
    id: 'sofas',
    label: 'Мягкая мебель',
    to: '/catalog/miagkaia-mebel',
    groups: [
      {
        title: 'Диваны',
        titleTo: '/catalog/divany',
        links: [{ to: '/catalog/pryamye-divany', label: 'Прямые диваны' }],
      },
      { links: [{ to: '/catalog/kresla', label: 'Кресла' }] },
    ],
  },
  {
    id: 'kitchens',
    label: 'Кухни',
    to: '/catalog/kuhni',
    groups: [{ links: [{ to: '/catalog/kuhonnye-ugolki', label: 'Кухонные уголки' }] }],
  },
];

/** Ширина телефона (до 550) — для медиазапроса max-width: 549.98px */
let mobile = false;

beforeAll(() => {
  // jsdom без matchMedia
  window.matchMedia = ((query: string) => ({
    matches: mobile && query.includes('549.98'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

beforeEach(() => {
  mobile = false;
});

function renderHeader() {
  render(
    <MemoryRouter>
      <Header catalogSections={sections} roomsSections={sections} />
      <main>Главная страница</main>
    </MemoryRouter>,
  );
  const catalog = screen.getByRole('button', { name: 'Каталог' });
  fireEvent.click(catalog);
  expect(catalog).toHaveAttribute('aria-expanded', 'true');
  return catalog;
}

describe('Header menu', () => {
  it('closes when tapping outside the menu, but not inside it', () => {
    const catalog = renderHeader();

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Подразделы: Кухни' }));
    expect(catalog).toHaveAttribute('aria-expanded', 'true');

    fireEvent.pointerDown(screen.getByText('Главная страница'));
    expect(catalog).toHaveAttribute('aria-expanded', 'false');
  });

  it('locks page scroll while the menu is open on a phone', () => {
    mobile = true;
    const catalog = renderHeader();
    expect(document.documentElement.style.overflow).toBe('hidden');

    fireEvent.click(catalog);
    expect(catalog).toHaveAttribute('aria-expanded', 'false');
    expect(document.documentElement.style.overflow).toBe('');
  });

  it('keeps page scroll on wider screens', () => {
    renderHeader();
    expect(document.documentElement.style.overflow).toBe('');
  });
});

describe('Разделы с подразделами', () => {
  /** Запросы внутри меню: в полосе категорий шапки те же названия */
  const menu = () => within(screen.getByRole('region', { name: 'Каталог' }));

  /** Пункт левой колонки: ссылка на раздел и шеврон рядом */
  const sectionRow = (name: string) =>
    menu()
      .getByRole('button', { name: `Подразделы: ${name}` })
      .closest('div')!;

  it('подпись раздела — ссылка на сам раздел, она же закрывает меню', () => {
    const catalog = renderHeader();

    const link = within(sectionRow('Мягкая мебель')).getByRole('link');
    expect(link).toHaveAttribute('href', '/catalog/miagkaia-mebel');

    fireEvent.click(link);
    expect(catalog).toHaveAttribute('aria-expanded', 'false');
  });

  it('наведение на раздел показывает его подразделы', () => {
    renderHeader();
    expect(menu().queryByRole('link', { name: 'Кухонные уголки' })).not.toBeInTheDocument();

    fireEvent.mouseEnter(sectionRow('Кухни'));
    expect(menu().getByRole('link', { name: 'Кухонные уголки' })).toBeInTheDocument();
  });

  it('шеврон раскрывает раздел без перехода — для касания и клавиатуры', () => {
    const catalog = renderHeader();

    fireEvent.click(menu().getByRole('button', { name: 'Подразделы: Кухни' }));
    expect(catalog).toHaveAttribute('aria-expanded', 'true');
    expect(menu().getByRole('link', { name: 'Кухонные уголки' })).toBeInTheDocument();
  });

  it('третий уровень: заголовок колонки ведёт на подраздел, под ним его дети', () => {
    renderHeader();

    expect(menu().getByRole('link', { name: 'Диваны' })).toHaveAttribute('href', '/catalog/divany');
    expect(menu().getByRole('link', { name: 'Прямые диваны' })).toHaveAttribute(
      'href',
      '/catalog/pryamye-divany',
    );
  });
});
