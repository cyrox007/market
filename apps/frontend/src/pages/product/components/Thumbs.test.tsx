import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MoreThumbsButton, ThumbImage } from './Thumbs';

describe('превью фото', () => {
  it('выбранное — с затемнением, остальные без', () => {
    const { container, rerender } = render(<ThumbImage src="/a.jpg" active />);
    expect(container.querySelector('span[aria-hidden]')).toBeInTheDocument();
    rerender(<ThumbImage src="/a.jpg" active={false} />);
    expect(container.querySelector('span[aria-hidden]')).not.toBeInTheDocument();
    expect(container.querySelector('img')).toHaveAttribute('loading', 'lazy');
  });

  it('«ещё превью» — кнопка с подписью, место задаёт вызывающий', () => {
    const onClick = vi.fn();
    render(
      <MoreThumbsButton
        label="Ещё превью"
        direction="right"
        onClick={onClick}
        className="right-0 w-9"
      />,
    );
    const button = screen.getByRole('button', { name: 'Ещё превью' });
    expect(button).toHaveClass('absolute', 'right-0', 'w-9', 'motion-reduce:transition-none');
    fireEvent.click(button);
    expect(onClick).toHaveBeenCalledOnce();
  });
});
