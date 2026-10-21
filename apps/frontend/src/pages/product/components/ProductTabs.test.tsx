import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import ProductTabs, { type ProductTabId } from './ProductTabs';

const specs = [
  { slug: 'width', name: 'Ширина, мм', value: '1500' },
  { slug: 'body', name: 'Материал корпуса', value: 'ЛДСП' },
];

function Tabs(props: Partial<Parameters<typeof ProductTabs>[0]> & { initial?: ProductTabId }) {
  const [tab, setTab] = useState<ProductTabId>(props.initial ?? 'description');
  return (
    <ProductTabs
      activeTab={tab}
      onTabChange={setTab}
      description="<p>Тумба «Пандора»</p>"
      specifications={specs}
      reviewsCount={12}
      reviews={<p>Список отзывов</p>}
      {...props}
    />
  );
}

describe('вкладки карточки товара', () => {
  it('три вкладки, у отзывов — число; активна «Описание»', () => {
    render(<Tabs />);
    const tabs = screen.getAllByRole('tab');
    expect(tabs.map((t) => t.textContent)).toEqual(['Описание', 'Характеристики', 'Отзывы12']);
    expect(screen.getByRole('tab', { name: 'Описание' })).toHaveAttribute('aria-selected', 'true');
  });

  it('содержимое всех вкладок в разметке, неактивные скрыты', () => {
    render(<Tabs />);
    expect(screen.getByText('Тумба «Пандора»')).toBeVisible();
    expect(screen.getByText('ЛДСП')).not.toBeVisible();
    expect(screen.getByText('Список отзывов')).not.toBeVisible();
  });

  it('переключение по щелчку и стрелками', () => {
    render(<Tabs />);
    fireEvent.click(screen.getByRole('tab', { name: 'Характеристики' }));
    expect(screen.getByText('ЛДСП')).toBeVisible();
    expect(screen.getByText('Тумба «Пандора»')).not.toBeVisible();

    fireEvent.keyDown(screen.getByRole('tab', { name: 'Характеристики' }), { key: 'ArrowRight' });
    expect(screen.getByRole('tab', { name: /Отзывы/ })).toHaveFocus();
    expect(screen.getByText('Список отзывов')).toBeVisible();
    fireEvent.keyDown(screen.getByRole('tab', { name: /Отзывы/ }), { key: 'ArrowRight' });
    expect(screen.getByRole('tab', { name: 'Описание' })).toHaveAttribute('aria-selected', 'true');
  });

  it('характеристики — название и значение строкой', () => {
    render(<Tabs initial="specs" />);
    const row = screen.getByText('Ширина, мм').closest('div');
    expect(row).toHaveTextContent('Ширина, мм1500');
  });

  it('пусто — вкладки не прячутся, заглушки; без отзывов — без числа', () => {
    render(<Tabs description="<p> </p>" specifications={[]} reviewsCount={0} />);
    expect(screen.getAllByRole('tab').map((t) => t.textContent)).toEqual([
      'Описание',
      'Характеристики',
      'Отзывы',
    ]);
    expect(screen.getByRole('heading', { name: 'Описания пока нет' })).toBeVisible();
    expect(screen.getByText('Характеристик пока нет')).not.toBeVisible();
  });

  it('у всех панелей одна минимальная высота — блок не скачет при переключении', () => {
    render(<Tabs />);
    const panels = screen.getAllByRole('tabpanel', { hidden: true });
    const minHeights = panels.map((panel) => panel.className.match(/(^| )min-h-\S+/g)?.join());
    expect(minHeights[0]).toBeTruthy();
    expect(new Set(minHeights).size).toBe(1);
  });
});
