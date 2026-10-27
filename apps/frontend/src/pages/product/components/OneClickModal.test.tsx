import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OneClickModal from './OneClickModal';

// Маска — react-imask, в тестах не нужна: проверяем разбор того, что в поле
vi.mock('../../../components/ui/PhoneInput', () => ({
  default: ({
    value,
    onChange,
    ...rest
  }: {
    value: string;
    onChange: (e: { target: { value: string } }) => void;
  }) => <input {...rest} value={value} onChange={onChange} />,
}));

beforeEach(() => {
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

const onClose = vi.fn();

const setup = () =>
  render(
    <MemoryRouter>
      <OneClickModal open onClose={onClose} />
    </MemoryRouter>,
  );

const submit = () => screen.getByRole('button', { name: 'Оформить заказ' });
const name = () => screen.getByPlaceholderText('ФИО');
const phone = () => screen.getByLabelText('Телефон');
const offer = () => screen.getByRole('checkbox', { name: /Публичной оферты/ });
const personal = () => screen.getByRole('checkbox', { name: /персональных данных/ });

/** Заполнить так, чтобы проверка полей прошла */
function fillValid() {
  fireEvent.change(name(), { target: { value: 'Иванов Иван' } });
  fireEvent.change(phone(), { target: { value: '+7 (912) 345-67-89' } });
  fireEvent.click(offer());
  fireEvent.click(personal());
}

describe('окно «Купить в один клик»', () => {
  it('поля, галочки со ссылками и кнопка; согласия не отмечены заранее', () => {
    setup();

    expect(screen.getByRole('dialog', { name: 'Купить в один клик' })).toBeInTheDocument();
    expect(name()).toBeInTheDocument();
    expect(phone()).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Комментарий к заказу')).toBeInTheDocument();

    // 152-ФЗ: согласие ставит сам покупатель (market-docs/44, вопрос 7)
    expect(offer()).not.toBeChecked();
    expect(personal()).not.toBeChecked();

    expect(screen.getByRole('link', { name: 'Публичной оферты' })).toHaveAttribute('href', '/oferta');
    expect(screen.getByRole('link', { name: 'персональных данных' })).toHaveAttribute(
      'href',
      '/privacy',
    );
    expect(submit()).toBeInTheDocument();
  });

  it('пустая форма: отправить нельзя, под каждым полем своя подсказка', () => {
    setup();
    fireEvent.click(submit());

    expect(screen.getByText('Укажите ФИО')).toBeInTheDocument();
    expect(screen.getByText('Укажите телефон полностью')).toBeInTheDocument();
    expect(screen.getByText('Отметьте оба согласия')).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('неполный телефон — тоже не отправить, остальные подсказки уходят', () => {
    setup();
    fireEvent.change(name(), { target: { value: 'Иванов Иван' } });
    fireEvent.change(phone(), { target: { value: '+7 (912) 345-67-8' } });
    fireEvent.click(offer());
    fireEvent.click(personal());
    fireEvent.click(submit());

    expect(screen.getByText('Укажите телефон полностью')).toBeInTheDocument();
    expect(screen.queryByText('Укажите ФИО')).not.toBeInTheDocument();
    expect(screen.queryByText('Отметьте оба согласия')).not.toBeInTheDocument();
  });

  it('одной галочки мало', () => {
    setup();
    fireEvent.change(name(), { target: { value: 'Иванов Иван' } });
    fireEvent.change(phone(), { target: { value: '+7 (912) 345-67-89' } });
    fireEvent.click(offer());
    fireEvent.click(submit());

    expect(screen.getByText('Отметьте оба согласия')).toBeInTheDocument();
  });

  it('форма заполнена: приёма заявок нет — сообщение с телефоном, введённое остаётся', () => {
    setup();
    fillValid();
    fireEvent.change(screen.getByPlaceholderText('Комментарий к заказу'), {
      target: { value: 'Позвоните после 18' },
    });
    fireEvent.click(submit());

    expect(screen.getByRole('alert')).toHaveTextContent(
      'Оформление в один клик пока не работает',
    );
    expect(screen.getByRole('link', { name: '8 (800) 222-85-86' })).toHaveAttribute(
      'href',
      'tel:88002228586',
    );

    expect(name()).toHaveValue('Иванов Иван');
    expect(phone()).toHaveValue('+7 (912) 345-67-89');
    expect(screen.getByPlaceholderText('Комментарий к заказу')).toHaveValue('Позвоните после 18');
    expect(offer()).toBeChecked();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('правка поля убирает его подсказку, остальные держатся', () => {
    setup();
    fireEvent.click(submit());
    expect(screen.getByText('Укажите ФИО')).toBeInTheDocument();

    fireEvent.change(name(), { target: { value: 'Иванов Иван' } });

    expect(screen.queryByText('Укажите ФИО')).not.toBeInTheDocument();
    expect(screen.getByText('Укажите телефон полностью')).toBeInTheDocument();
  });
});
