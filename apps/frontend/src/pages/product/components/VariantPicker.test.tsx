import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { VariantAttributeView } from '../hooks/useVariantSelection';
import VariantPicker from './VariantPicker';

const COLOR: VariantAttributeView = {
  slug: 'color',
  name: 'Цвет',
  isColor: true,
  selectedName: 'Тёмный лазурный',
  values: [
    { slug: 'azure', name: 'Тёмный лазурный', code: '#1D4E89', selected: true, available: true },
    { slug: 'beige', name: 'Бежевый', code: '#F5F5DC', selected: false, available: true },
    { slug: 'red', name: 'Красный', code: '#E23B2E', selected: false, available: false },
  ],
};

const SIZE: VariantAttributeView = {
  slug: 'size',
  name: 'Размеры матраса/наматрасника',
  isColor: false,
  selectedName: '90×200 см',
  values: [
    { slug: '90', name: '90×200 см', code: null, selected: true, available: true },
    { slug: '160', name: '160×200 см', code: null, selected: false, available: false },
  ],
};

describe('выбор варианта', () => {
  it('цвет — квадраты с подписью выбранного значения', () => {
    render(<VariantPicker attributes={[COLOR]} onSelect={() => {}} />);
    expect(screen.getByText('Цвет: тёмный лазурный')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Тёмный лазурный' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(screen.getByRole('button', { name: 'Бежевый' })).toHaveAttribute(
      'aria-pressed',
      'false',
    );
  });

  it('недоступное сочетание помечено, но выбирается — даст ближайший вариант', () => {
    const onSelect = vi.fn();
    render(<VariantPicker attributes={[COLOR]} onSelect={onSelect} />);
    const red = screen.getByRole('button', { name: 'Красный, нет в этом сочетании' });
    fireEvent.click(red);
    expect(onSelect).toHaveBeenCalledWith('color', 'red');
  });

  it('прочий атрибут — выпадающий список с выбранным значением', () => {
    const onSelect = vi.fn();
    render(<VariantPicker attributes={[COLOR, SIZE]} onSelect={onSelect} />);
    const select = screen.getByRole('combobox', { name: 'Размеры матраса/наматрасника:' });
    expect(select).toHaveValue('90');
    expect(
      screen.getByRole('option', { name: '160×200 см, нет в этом сочетании' }),
    ).toBeInTheDocument();
    fireEvent.change(select, { target: { value: '160' } });
    expect(onSelect).toHaveBeenCalledWith('size', '160');
  });

  it('пока товар перезагружается — выбор недоступен', () => {
    render(<VariantPicker attributes={[COLOR, SIZE]} onSelect={() => {}} disabled />);
    expect(screen.getByRole('button', { name: 'Бежевый' })).toBeDisabled();
    expect(screen.getByRole('combobox')).toBeDisabled();
  });

  it('атрибутов нет — блока нет', () => {
    const { container } = render(<VariantPicker attributes={[]} onSelect={() => {}} />);
    expect(container).toBeEmptyDOMElement();
  });
});
