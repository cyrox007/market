import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Radio, RadioGroup } from './Radio';

const renderSort = (value: string, onChange = vi.fn()) => {
  render(
    <RadioGroup label="Сортировка" value={value} onChange={onChange}>
      <Radio value="popular" label="По популярности" />
      <Radio value="cheap" label="Сначала дешевле" />
      <Radio value="expensive" label="Сначала дороже" />
      <Radio value="rating" label="По рейтингу" disabled />
    </RadioGroup>,
  );
  return onChange;
};

describe('RadioGroup', () => {
  it('is a named group with the current option checked', () => {
    renderSort('cheap');

    expect(screen.getByRole('radiogroup', { name: 'Сортировка' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Сначала дешевле' })).toBeChecked();
    expect(screen.getByRole('radio', { name: 'По популярности' })).not.toBeChecked();
  });

  it('reports the picked value', () => {
    const onChange = renderSort('popular');
    fireEvent.click(screen.getByText('Сначала дороже'));
    expect(onChange).toHaveBeenCalledWith('expensive');
  });

  it('shares one name so the browser moves between options with arrows', () => {
    renderSort('popular');
    const names = screen.getAllByRole('radio').map((r) => r.getAttribute('name'));
    expect(new Set(names).size).toBe(1);
    expect(names[0]).toBeTruthy();
  });

  it('ignores a disabled option', () => {
    const onChange = renderSort('popular');
    expect(screen.getByRole('radio', { name: 'По рейтингу' })).toBeDisabled();
    fireEvent.click(screen.getByText('По рейтингу'));
    expect(onChange).not.toHaveBeenCalled();
  });
});
