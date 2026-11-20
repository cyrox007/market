import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, expect, it } from 'vitest';
import type { OrderItem, OrderManagerContact } from '../../lib/api';
import OrderItemsList from './OrderItemsList';
import OrderManagerContacts from './OrderManagerContacts';

afterEach(cleanup);

it('renders historical order composition rather than current product values', () => {
  const item = {
    id: 1, product_id: 2, quantity: 1, price: 100, total: 100,
    product: { name: 'Изменённый товар', slug: 'current' },
    display: {
      source: 'snapshot', version: 1, name: 'Купленный диван', article: '437667',
      colors: [{ element: { id: 1, name: 'Фасад', slug: 'fasad' }, solution: { id: 1, name: 'Белый', slug: 'white', icon_url: null, base_colors: [{ id: 1, name: 'Белый', slug: 'white', hex: '#FFFFFF' }] } }],
      materials: [{ slug: 'material', name: 'Материал', value: 'ЛДСП' }],
      configuration: [{ slug: 'variant', name: 'Вариант', value: 'Бока Бруклин' }],
    },
  } as OrderItem;
  render(<MemoryRouter><OrderItemsList items={[item]} /></MemoryRouter>);
  expect(screen.getByText('Купленный диван')).toBeInTheDocument();
  expect(screen.queryByText('Изменённый товар')).not.toBeInTheDocument();
  expect(screen.getByText('Фасад: Белый')).toBeInTheDocument();
  expect(screen.getByText('Материал: ЛДСП')).toBeInTheDocument();
  expect(screen.getByText('Вариант: Бока Бруклин')).toBeInTheDocument();
});

it('does not substitute current configuration into a legacy order', () => {
  const item = { id: 1, product_id: 2, quantity: 1, price: 100, total: 100,
    display: { source: 'legacy', name: 'Старый диван', colors: [], materials: [], configuration: [] },
    product: { name: 'Новое имя', slug: 'current' },
  } as unknown as OrderItem;
  render(<MemoryRouter><OrderItemsList items={[item]} /></MemoryRouter>);
  expect(screen.getByText('Старый диван')).toBeInTheDocument();
  expect(screen.queryByText('Новое имя')).not.toBeInTheDocument();
});

it('uses contact actions returned for this order', () => {
  const contact = {
    primary_action: { type: 'phone', label: 'Позвонить менеджеру', url: 'tel:+74742222222' },
    actions: [{ type: 'phone', label: 'Позвонить менеджеру', url: 'tel:+74742222222' },
      { type: 'email', label: 'Написать менеджеру', url: 'mailto:regional@example.test?subject=Order' }],
    hours: '10:00–18:00',
  } as OrderManagerContact;
  render(<OrderManagerContacts contact={contact} />);
  expect(screen.getByRole('link', { name: 'Связаться с менеджером' })).toHaveAttribute('href', 'tel:+74742222222');
  expect(screen.getByRole('link', { name: 'Написать менеджеру' })).toHaveAttribute('href', 'mailto:regional@example.test?subject=Order');
});

it('hides contact action when no contact is configured', () => {
  render(<OrderManagerContacts />);
  expect(screen.queryByRole('link')).not.toBeInTheDocument();
});
