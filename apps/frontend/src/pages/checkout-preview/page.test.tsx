import { cleanup, fireEvent, render, screen, within, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import CheckoutPreview from './page';

beforeEach(() => {
  vi.stubGlobal(
    'matchMedia',
    vi.fn((media: string) => ({
      media,
      matches: false,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  );
});
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

it('adds optional services to the total and keeps required assembly selected', () => {
  render(
    <MemoryRouter>
      <CheckoutPreview />
    </MemoryRouter>,
  );
  fireEvent.click(screen.getByRole('checkbox', { name: /Сборка мебели/ }));
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent('365 000');
  fireEvent.click(screen.getByRole('button', { name: 'Дополнительные услуги' }));
  expect(screen.getByRole('button', { name: 'Дополнительные услуги' })).toHaveAttribute(
    'aria-expanded',
    'false',
  );
  fireEvent.click(screen.getByRole('button', { name: 'Дополнительные услуги' }));
  expect(screen.getByRole('checkbox', { name: /Сборка мебели/ })).toBeChecked();
  fireEvent.click(screen.getByRole('checkbox', { name: /Вывоз упаковки/ }));
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent('365 000');
  fireEvent.click(screen.getByRole('checkbox', { name: /Подъём/ }));
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent('366 000');
  fireEvent.click(screen.getByRole('checkbox', { name: 'Обязательная сборка (демо)' }));
  expect(screen.getByRole('checkbox', { name: /Сборка мебели/ })).toBeDisabled();
  expect(screen.getByRole('checkbox', { name: /Сборка мебели/ })).toBeChecked();
  fireEvent.click(screen.getByRole('checkbox', { name: 'Услуги настроены (демо)' }));
  expect(screen.queryByRole('region', { name: 'Дополнительные услуги' })).not.toBeInTheDocument();
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent('362 000');
  fireEvent.click(screen.getByRole('radio', { name: 'Самовывоз' }));
  expect(screen.getByLabelText('Итоговая стоимость')).toHaveTextContent('360 000');
});

it('lets the owner preview an enabled checkout without sending an order or payment', async () => {
  const fetch = vi
    .spyOn(globalThis, 'fetch')
    .mockRejectedValue(new Error('Preview must not use API'));
  render(
    <MemoryRouter>
      <CheckoutPreview />
    </MemoryRouter>,
  );
  expect(screen.getByRole('button', { name: 'Оформить заказ' })).toBeEnabled();
  fireEvent.click(screen.getByRole('button', { name: 'Оформить заказ' }));
  expect(await screen.findByText('Заказ не создан. Деньги не списывались.')).toBeInTheDocument();
  expect(fetch).not.toHaveBeenCalled();
});

it('shows the pickup notice after choosing pickup and hides the delivery calendar', () => {
  render(
    <MemoryRouter>
      <CheckoutPreview />
    </MemoryRouter>,
  );
  fireEvent.click(screen.getByRole('radio', { name: 'Самовывоз' }));
  expect(screen.getByText(/Обратите внимание, товар можно забрать/)).toHaveTextContent(
    'с 02.11.26 по 08.11.26',
  );
  expect(
    screen.queryByRole('button', { name: 'Открыть календарь доставки' }),
  ).not.toBeInTheDocument();
});

it('selects a day and interval, resets an unavailable interval, and chooses a date from the next calendar month', async () => {
  render(
    <MemoryRouter>
      <CheckoutPreview />
    </MemoryRouter>,
  );
  fireEvent.click(screen.getByRole('button', { name: '09:00 – 14:00 МСК' }));
  fireEvent.click(screen.getByRole('button', { name: /4 ноября 2026/ }));
  expect(screen.getByLabelText('Выбранная доставка')).toHaveTextContent('с 14:00 до 17:00');
  expect(screen.queryByRole('button', { name: '09:00 – 14:00 МСК' })).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Открыть календарь доставки' }));
  expect(await screen.findByRole('dialog', { name: 'Календарь доставки' })).toBeInTheDocument();
  expect(
    within(screen.getByRole('dialog', { name: 'Календарь доставки' })).getByRole('button', {
      name: /^8 ноября 2026/,
    }),
  ).toBeDisabled();
  fireEvent.click(screen.getByRole('button', { name: 'Следующий месяц' }));
  fireEvent.click(screen.getByRole('button', { name: /15 декабря 2026/ }));
  expect(screen.getByLabelText('Выбранная доставка')).toHaveTextContent('15 декабря 2026');
  await waitFor(() =>
    expect(screen.queryByRole('dialog', { name: 'Календарь доставки' })).not.toBeInTheDocument(),
  );
  fireEvent.click(screen.getByRole('button', { name: 'Открыть календарь доставки' }));
  fireEvent.keyDown(document, { key: 'Escape' });
  expect(screen.getByRole('button', { name: 'Открыть календарь доставки' })).toHaveFocus();
});
