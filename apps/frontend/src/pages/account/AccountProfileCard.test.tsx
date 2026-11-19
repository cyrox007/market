import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import AccountProfileCard from './AccountProfileCard';

afterEach(cleanup);

it('shows the current buyer, formatted phone and actual newsletter preference', () => {
  const edit = vi.fn();
  render(<AccountProfileCard
    user={{ name: 'Анна Смирнова', email: 'anna@example.test', phone: '+79103561842' }}
    newsletter={{ enabled: false, isLoading: false, onRetry: vi.fn() }}
    onEdit={edit}
  />);
  expect(screen.getByRole('region', { name: 'Профиль покупателя' })).toBeInTheDocument();
  expect(screen.getByRole('heading', { name: 'Анна Смирнова' })).toBeInTheDocument();
  expect(screen.getByText('АС')).toBeInTheDocument();
  expect(screen.queryByText('Липецк')).not.toBeInTheDocument();
  expect(screen.getByText('anna@example.test')).toBeInTheDocument();
  expect(screen.getByText('+7 (910) 356-18-42')).toBeInTheDocument();
  expect(screen.getByText('Рассылка отключена')).toBeInTheDocument();
  expect(screen.queryByText('Акции и скидки на email')).not.toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Изменить профиль' }));
  expect(edit).toHaveBeenCalledTimes(1);
});

it('does not claim the newsletter is disabled while it is loading or unavailable', () => {
  const retry = vi.fn();
  const props = {
    user: { name: 'Анна', email: 'anna@example.test' },
    onEdit: vi.fn(),
  };
  const { rerender } = render(<AccountProfileCard {...props} newsletter={{ isLoading: true, onRetry: retry }} />);
  expect(screen.queryByRole('status', { name: 'Загрузка города' })).not.toBeInTheDocument();
  expect(screen.getByRole('status', { name: 'Загрузка рассылки' })).toBeInTheDocument();
  expect(screen.queryByText('Рассылка отключена')).not.toBeInTheDocument();
  rerender(<AccountProfileCard {...props} newsletter={{ isLoading: false, error: new Error('offline'), onRetry: retry }} />);
  expect(screen.getByText('Не удалось загрузить')).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: 'Повторить загрузку рассылки' }));
  expect(retry).toHaveBeenCalledTimes(1);
  rerender(<AccountProfileCard {...props} newsletter={{ enabled: true, isLoading: false, onRetry: retry }} />);
  expect(screen.getByText('Акции и скидки на email')).toBeInTheDocument();
  expect(screen.queryByRole('status', { name: 'Загрузка города' })).not.toBeInTheDocument();
});

it('handles absent contacts without copying sample data from the design', () => {
  render(<AccountProfileCard user={{ name: '', email: '' }}
    newsletter={{ enabled: false, isLoading: false, onRetry: vi.fn() }} onEdit={vi.fn()} />);
  expect(screen.getByRole('heading', { name: 'Покупатель' })).toBeInTheDocument();
  expect(screen.getAllByText('Не указан')).toHaveLength(2);
  expect(screen.queryByText('Липецк')).not.toBeInTheDocument();
  expect(screen.queryByText('АС')).not.toBeInTheDocument();
});
