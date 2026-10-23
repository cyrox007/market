import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ReviewModal from './ReviewModal';

const create = vi.fn();
vi.mock('../../../lib/api', () => ({
  api: { reviews: { create: (...a: unknown[]) => create(...a) } },
}));

beforeEach(() => {
  create.mockReset().mockResolvedValue({ message: 'ok' });
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

const onClose = vi.fn();
const setup = () => render(<ReviewModal open onClose={onClose} productId={82} authorName="Анна" />);
const TEXT = 'Диван удобный, собрали за час, ткань приятная.';
const send = () => screen.getByRole('button', { name: 'Отправить отзыв' });

describe('окно «Отзыв о товаре»', () => {
  it('заголовок, оценка, поле, подсказка и счётчик', () => {
    setup();
    expect(screen.getByRole('dialog', { name: 'Отзыв о товаре' })).toBeInTheDocument();
    expect(screen.getByRole('radiogroup', { name: 'Ваша оценка' })).toBeInTheDocument();
    expect(screen.getAllByRole('radio')).toHaveLength(5);
    expect(
      screen.getByPlaceholderText('Расскажите о покупке: качество, сборка, доставка'),
    ).toHaveAttribute('maxLength', '1000');
    expect(screen.getByText('От 25 до 1000 символов')).toBeInTheDocument();
    expect(screen.getByText('0 из 1000')).toBeInTheDocument();
    expect(screen.getByText('Отзыв появится на сайте после проверки')).toBeInTheDocument();
  });

  it('без оценки или с текстом короче 25 — отправить нельзя', () => {
    setup();
    const field = screen.getByRole('textbox');
    fireEvent.change(field, { target: { value: TEXT } });
    expect(screen.getByText(`${TEXT.length} из 1000`)).toBeInTheDocument();
    expect(send()).toBeDisabled();
    fireEvent.click(screen.getByRole('radio', { name: '4 из 5' }));
    expect(send()).toBeEnabled();
    fireEvent.change(field, { target: { value: 'Коротко' } });
    expect(send()).toBeDisabled();
  });

  it('отправка с именем из профиля → «Отзыв отправлен», «Вернуться к товару» закрывает', async () => {
    setup();
    fireEvent.click(screen.getByRole('radio', { name: '5 из 5' }));
    fireEvent.change(screen.getByRole('textbox'), { target: { value: `  ${TEXT}  ` } });
    fireEvent.click(send());
    await screen.findByText('Спасибо, что поделились мнением');
    expect(create).toHaveBeenCalledWith(82, { name: 'Анна', rating: 5, comment: TEXT });
    expect(screen.getByRole('dialog', { name: 'Отзыв отправлен' })).toBeInTheDocument();
    expect(
      screen.getByText('Отзыв появится на странице товара после проверки'),
    ).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Вернуться к товару' }));
    expect(onClose).toHaveBeenCalled();
  });

  it('ошибка — сообщение, текст и оценка на месте', async () => {
    create.mockRejectedValue(
      Object.assign(new Error('500'), { data: { message: 'Сервер недоступен' } }),
    );
    setup();
    fireEvent.click(screen.getByRole('radio', { name: '3 из 5' }));
    fireEvent.change(screen.getByRole('textbox'), { target: { value: TEXT } });
    fireEvent.click(send());
    expect(await screen.findByRole('alert')).toHaveTextContent('Сервер недоступен');
    expect(screen.getByRole('textbox')).toHaveValue(TEXT);
    expect(screen.getByRole('radio', { name: '3 из 5' })).toBeChecked();
    await waitFor(() => expect(send()).toBeEnabled());
  });
});
