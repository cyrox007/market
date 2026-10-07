import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import Header from './Header';
import type { MenuSection } from './lib/menu-types';

const sections: MenuSection[] = [
  { id: 'sofas', label: 'Мягкая мебель', groups: [{ links: [{ to: '/catalog/divany', label: 'Диваны' }] }] },
  { id: 'kitchens', label: 'Кухни', groups: [{ links: [{ to: '/catalog/kuhni', label: 'Кухни' }] }] },
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

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Кухни' }));
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
