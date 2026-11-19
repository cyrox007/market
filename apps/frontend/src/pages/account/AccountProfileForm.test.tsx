import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import AccountProfileForm from './AccountProfileForm';
import { renderToString } from 'react-dom/server';
afterEach(cleanup);
const user = { name: 'Анна Смирнова', email: 'anna@example.test', phone: '+79103561842' };
it('keeps structured names when saving and never sends legacy name', async () => {
  const save = vi.fn().mockResolvedValue(undefined);
  render(<AccountProfileForm user={{ ...user, first_name: 'Анна', last_name: 'Смирнова', patronymic: 'Игоревна' }} onSave={save} />);
  expect(screen.queryByLabelText('Сохранённое полное имя')).not.toBeInTheDocument();
  expect(screen.getByLabelText('Имя')).toHaveValue('Анна');
  expect(screen.getByLabelText('Фамилия')).toHaveValue('Смирнова');
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  await waitFor(() => expect(save).toHaveBeenCalledWith({ first_name: 'Анна', last_name: 'Смирнова', patronymic: 'Игоревна', phone: '+79103561842' }));
});
it('does not split legacy names and requires both parts before migration', async () => {
  const save = vi.fn().mockRejectedValueOnce({ data: { errors: { last_name: ['Проверьте фамилию'] } } }).mockResolvedValueOnce(undefined);
  render(<AccountProfileForm user={user} onSave={save} />);
  expect(screen.getByLabelText('Имя')).toHaveValue('');
  expect(screen.getByLabelText('Фамилия')).toHaveValue('');
  fireEvent.change(screen.getByLabelText('Имя'), { target: { value: '  Анна-Мария  ' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(screen.getByLabelText('Фамилия')).toHaveAttribute('aria-invalid', 'true');
  expect(save).not.toHaveBeenCalled();
  fireEvent.change(screen.getByLabelText('Фамилия'), { target: { value: '  Смирнова Петрова  ' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(await screen.findByText('Проверьте фамилию')).toBeInTheDocument();
  expect(screen.getByLabelText('Фамилия')).toHaveFocus();
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Изменения сохранены');
  expect(save).toHaveBeenLastCalledWith({ first_name: 'Анна-Мария', last_name: 'Смирнова Петрова', patronymic: null, phone: '+79103561842' });
});
it('saves one profile with a whole name and read-only email, blocking repeated submits', async () => {
  let finish!: () => void;
  const save = vi.fn(() => new Promise<void>(resolve => { finish = resolve; }));
  render(<AccountProfileForm user={user} onSave={save} />);
  expect(screen.getByLabelText('Email')).toHaveAttribute('readonly');
  fireEvent.change(screen.getByLabelText('Сохранённое полное имя'), { target: { value: '  Анна Петрова  ' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  fireEvent.submit(screen.getByRole('form', { name: 'Личные данные' }));
  expect(save).toHaveBeenCalledTimes(1);
  expect(save).toHaveBeenCalledWith({ name: 'Анна Петрова', phone: '+79103561842' });
  expect(screen.getByLabelText('Сохранённое полное имя')).toBeDisabled();
  finish();
  expect(await screen.findByRole('status')).toHaveTextContent('Изменения сохранены');
});
it('validates blank name and incomplete phone before saving and focuses the first error', async () => {
  const save = vi.fn();
  render(<AccountProfileForm user={user} onSave={save} />);
  fireEvent.change(screen.getByLabelText('Сохранённое полное имя'), { target: { value: ' ' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(screen.getByLabelText('Сохранённое полное имя')).toHaveAttribute('aria-invalid', 'true');
  expect(screen.getByLabelText('Сохранённое полное имя')).toHaveFocus();
  expect(save).not.toHaveBeenCalled();
  fireEvent.change(screen.getByLabelText('Сохранённое полное имя'), { target: { value: 'Анна' } });
  fireEvent.input(screen.getByLabelText('Телефон'), { target: { value: '+7 (910) 356-18' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(screen.getByLabelText('Телефон')).toHaveAttribute('aria-invalid', 'true');
  expect(screen.getByLabelText('Телефон')).toHaveFocus();
  expect(save).not.toHaveBeenCalled();
});
it('keeps entered values on server field errors and supports retry and cancel', async () => {
  const save = vi.fn().mockRejectedValueOnce({ data: { errors: { phone: ['Этот номер уже используется'] } } }).mockResolvedValueOnce(undefined);
  render(<AccountProfileForm user={user} onSave={save} />);
  fireEvent.change(screen.getByLabelText('Сохранённое полное имя'), { target: { value: 'Анна Петрова' } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(await screen.findByText('Этот номер уже используется')).toBeInTheDocument();
  expect(screen.getByLabelText('Сохранённое полное имя')).toHaveValue('Анна Петрова');
  expect(screen.getByLabelText('Телефон')).toHaveFocus();
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Изменения сохранены');
  fireEvent.click(screen.getByRole('button', { name: 'Отменить изменения' }));
  expect(screen.getByLabelText('Сохранённое полное имя')).toHaveValue(user.name);
});
it('omits an empty phone and reports network failure separately from invalid fields', async () => {
  const save = vi.fn().mockRejectedValue(new Error('offline'));
  render(<AccountProfileForm user={{ ...user, phone: '' }} onSave={save} />);
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  await waitFor(() => expect(save).toHaveBeenCalledWith({ name: user.name, phone: undefined }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось сохранить изменения. Попробуйте ещё раз.');
  expect(screen.getByLabelText('Сохранённое полное имя')).not.toHaveAttribute('aria-invalid', 'true');
});
it('accepts 255 characters but rejects a 256-character name', async () => {
  const save = vi.fn().mockResolvedValue(undefined);
  render(<AccountProfileForm user={{ ...user, phone: '' }} onSave={save} />);
  fireEvent.change(screen.getByLabelText('Сохранённое полное имя'), { target: { value: 'А'.repeat(256) } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(screen.getByText('Имя должно содержать не более 255 символов')).toBeInTheDocument();
  expect(save).not.toHaveBeenCalled();
  fireEvent.change(screen.getByLabelText('Сохранённое полное имя'), { target: { value: 'А'.repeat(255) } });
  fireEvent.click(screen.getByRole('button', { name: 'Сохранить изменения' }));
  expect(await screen.findByRole('status')).toHaveTextContent('Изменения сохранены');
});

it('renders personal data on the server without browser storage', () => {
  const html = renderToString(<AccountProfileForm user={user} onSave={vi.fn()} />);
  expect(html).toContain('anna@example.test');
  expect(html).toContain('Личные данные');
});
