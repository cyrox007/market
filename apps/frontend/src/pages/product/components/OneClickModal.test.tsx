import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
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
  onClose.mockReset();
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

const onClose = vi.fn();

/** Показывает, куда ушёл переход и что приехало в state */
function CheckoutProbe() {
  const { search, state } = useLocation();
  return <p>{`checkout ${search} ${JSON.stringify((state as { oneClick?: unknown })?.oneClick ?? null)}`}</p>;
}

const setup = (url = '/product/divan', productId: number | null = 42, quantity = 1) =>
  render(
    <MemoryRouter initialEntries={[url]}>
      <Routes>
        <Route
          path="/product/:slug"
          element={<OneClickModal open onClose={onClose} productId={productId} quantity={quantity} />}
        />
        <Route path="/checkout" element={<CheckoutProbe />} />
      </Routes>
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

  it('форма заполнена — переход в прямое оформление с SKU, количеством и введённым', () => {
    setup('/product/divan', 42, 2);
    fillValid();
    fireEvent.change(screen.getByPlaceholderText('Комментарий к заказу'), {
      target: { value: 'Позвоните после 18' },
    });
    fireEvent.click(submit());

    expect(screen.getByText(/^checkout/)).toHaveTextContent(
      'checkout ?product_id=42&quantity=2 {"name":"Иванов Иван","phone":"+7 (912) 345-67-89","comment":"Позвоните после 18"}',
    );
    expect(onClose).toHaveBeenCalled();
  });

  it('закрытая цена по ссылке едет в оформление параметром offer', () => {
    setup('/product/divan?offer=7');
    fillValid();
    fireEvent.click(submit());

    expect(screen.getByText(/^checkout/)).toHaveTextContent('?product_id=42&quantity=1&offer=7');
  });

  it('вариант не выбран — просим выбрать, перехода нет', () => {
    setup('/product/divan', null);
    fillValid();
    fireEvent.click(submit());

    expect(screen.getByRole('alert')).toHaveTextContent('Сначала выберите вариант товара');
    expect(screen.queryByText(/^checkout/)).not.toBeInTheDocument();
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
