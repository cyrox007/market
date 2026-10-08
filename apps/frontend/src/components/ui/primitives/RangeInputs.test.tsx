import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import RangeInputs from './RangeInputs';
import type { RangeValue } from './RangeInputs';

const setup = (value: RangeValue = [null, null], onChange = vi.fn()) => {
  render(<RangeInputs label="Цена" min={890} max={180350} value={value} onChange={onChange} />);
  return {
    from: screen.getByRole('textbox', { name: 'Цена от' }) as HTMLInputElement,
    to: screen.getByRole('textbox', { name: 'Цена до' }) as HTMLInputElement,
    onChange,
  };
};

const type = (input: HTMLInputElement, text: string) => {
  fireEvent.focus(input);
  fireEvent.change(input, { target: { value: text } });
};

describe('RangeInputs', () => {
  it('shows the bounds as placeholders with digit groups', () => {
    const { from, to } = setup();
    expect(from).toHaveAttribute('placeholder', 'от 890');
    expect(to).toHaveAttribute('placeholder', 'до 180 350');
    expect(from).toHaveAttribute('inputMode', 'numeric');
  });

  it('shows chosen values with digit groups', () => {
    const { from, to } = setup([1070, 23500]);
    expect(from.value).toBe('1 070');
    expect(to.value).toBe('23 500');
  });

  it('groups digits while typing and drops anything but digits', () => {
    const { from } = setup();
    type(from, '23500р');
    expect(from.value).toBe('23 500');
  });

  it('commits on blur', () => {
    const { from, onChange } = setup();
    type(from, '23500');
    expect(onChange).not.toHaveBeenCalled();
    fireEvent.blur(from);
    expect(onChange).toHaveBeenCalledWith([23500, null]);
  });

  it('commits on Enter', () => {
    const { to, onChange } = setup();
    type(to, '50000');
    fireEvent.keyDown(to, { key: 'Enter' });
    expect(onChange).toHaveBeenCalledWith([null, 50000]);
  });

  it('pulls a value outside the bounds to the nearest bound', () => {
    const { from, to, onChange } = setup();
    type(from, '100');
    fireEvent.blur(from);
    expect(onChange).toHaveBeenLastCalledWith([890, null]);

    type(to, '999999');
    fireEvent.blur(to);
    expect(onChange).toHaveBeenLastCalledWith([null, 180350]);
  });

  it('swaps the ends when "from" is above "to"', () => {
    const { from, onChange } = setup([null, 5000]);
    type(from, '9000');
    fireEvent.blur(from);
    expect(onChange).toHaveBeenCalledWith([5000, 9000]);
  });

  it('clears a bound when the field is emptied', () => {
    const { to, onChange } = setup([1000, 5000]);
    type(to, '');
    fireEvent.blur(to);
    expect(onChange).toHaveBeenCalledWith([1000, null]);
  });

  it('does not report when nothing changed', () => {
    const { from, onChange } = setup([1000, 5000]);
    fireEvent.focus(from);
    fireEvent.blur(from);
    expect(onChange).not.toHaveBeenCalled();
  });

  it('follows the value coming from outside (e.g. the slider)', () => {
    const onChange = vi.fn();
    const { rerender } = render(
      <RangeInputs label="Цена" min={0} max={100} value={[10, 90]} onChange={onChange} />,
    );
    rerender(<RangeInputs label="Цена" min={0} max={100} value={[20, 80]} onChange={onChange} />);
    expect(screen.getByRole('textbox', { name: 'Цена от' })).toHaveValue('20');
    expect(screen.getByRole('textbox', { name: 'Цена до' })).toHaveValue('80');
  });
});
