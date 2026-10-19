import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PriceFields from './PriceFields';

const typeInto = (name: string, text: string) => {
  const input = screen.getByRole('textbox', { name });
  fireEvent.focus(input);
  fireEvent.change(input, { target: { value: text } });
  fireEvent.blur(input);
};

describe('цена в фильтрах', () => {
  it('«от» ниже минимума раздела не теряется, когда меняют «до»', () => {
    const onChange = vi.fn();
    // Границы расширены выбранным «от»: минимум раздела 18 243, в адресе — 5 000
    render(
      <PriceFields value={{ min: 5000 }} bounds={{ min: 5000, max: 180350 }} onChange={onChange} />,
    );
    typeInto('Цена до', '90000');
    expect(onChange).toHaveBeenLastCalledWith({ min: 5000, max: 90000 });
  });

  it('граница, сдвинутая на край раздела, — не фильтр', () => {
    const onChange = vi.fn();
    render(
      <PriceFields
        value={{ min: 20000, max: 90000 }}
        bounds={{ min: 18243, max: 180350 }}
        onChange={onChange}
      />,
    );
    typeInto('Цена от', '1');
    expect(onChange).toHaveBeenLastCalledWith({ max: 90000 });
  });
});
