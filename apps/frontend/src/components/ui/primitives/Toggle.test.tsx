import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Toggle from './Toggle';

describe('Toggle', () => {
  it('is a switch named by its label that flips on click', () => {
    const onChange = vi.fn();
    render(<Toggle label="По акции" checked={false} onChange={onChange} />);

    const sw = screen.getByRole('switch', { name: 'По акции' });
    expect(sw).toHaveAttribute('aria-checked', 'false');

    fireEvent.click(sw);
    expect(onChange).toHaveBeenCalledWith(true);
  });

  it('reports turning off when on', () => {
    const onChange = vi.fn();
    render(<Toggle label="По акции" checked onChange={onChange} />);

    const sw = screen.getByRole('switch', { name: 'По акции' });
    expect(sw).toHaveAttribute('aria-checked', 'true');
    fireEvent.click(sw);
    expect(onChange).toHaveBeenCalledWith(false);
  });

  it('does nothing when disabled', () => {
    const onChange = vi.fn();
    render(<Toggle label="По акции" checked={false} disabled onChange={onChange} />);

    fireEvent.click(screen.getByRole('switch', { name: 'По акции' }));
    expect(onChange).not.toHaveBeenCalled();
  });
});
