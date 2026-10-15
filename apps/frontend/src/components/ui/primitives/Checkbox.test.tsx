import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Checkbox from './Checkbox';

describe('Checkbox', () => {
  it('is found by its label and reports the new state on click', () => {
    const onChange = vi.fn();
    render(<Checkbox label="АСМ" checked={false} onChange={onChange} />);

    const box = screen.getByRole('checkbox', { name: 'АСМ' });
    expect(box).not.toBeChecked();

    fireEvent.click(screen.getByText('АСМ'));
    expect(onChange).toHaveBeenCalledWith(true);
  });

  it('shows the checked state', () => {
    render(<Checkbox label="Мебельный Рай" checked onChange={() => {}} />);
    expect(screen.getByRole('checkbox', { name: 'Мебельный Рай' })).toBeChecked();
  });

  it('ignores clicks when disabled', () => {
    const onChange = vi.fn();
    render(<Checkbox label="Кромма" checked={false} disabled onChange={onChange} />);

    const box = screen.getByRole('checkbox', { name: 'Кромма' });
    expect(box).toBeDisabled();
    fireEvent.click(screen.getByText('Кромма'));
    expect(onChange).not.toHaveBeenCalled();
  });
});
