import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Gallery from './Gallery';

const photos = (n: number) => Array.from({ length: n }, (_, i) => `/p${i + 1}.jpg`);

const thumbs = () =>
  within(screen.getByRole('list', { name: 'Фото товара' })).getAllByRole('button');

function renderGallery(images: string[], selectedIndex = 0) {
  const onSelectIndex = vi.fn();
  const onOpen = vi.fn();
  const utils = render(
    <Gallery
      images={images}
      productName="Диван"
      selectedIndex={selectedIndex}
      onSelectIndex={onSelectIndex}
      onOpen={onOpen}
    />,
  );
  return { ...utils, onSelectIndex, onOpen };
}

describe('галерея товара: столбик превью', () => {
  it('превью всех фото, выбранное отмечено', () => {
    renderGallery(photos(3), 1);
    expect(thumbs()).toHaveLength(3);
    expect(thumbs()[1]).toHaveAttribute('aria-current', 'true');
    expect(thumbs()[0]).not.toHaveAttribute('aria-current');
  });

  it('нажатие на превью выбирает фото', () => {
    const { onSelectIndex } = renderGallery(photos(3));
    fireEvent.click(thumbs()[2]);
    expect(onSelectIndex).toHaveBeenCalledWith(2);
  });

  it('фото до 4 — стрелки нет', () => {
    renderGallery(photos(4));
    expect(screen.queryByRole('button', { name: 'Следующие фото' })).not.toBeInTheDocument();
  });

  it('фото больше 4 — видно 4, стрелка листает по одному и с конца — в начало', () => {
    renderGallery(photos(6));
    const visible = () => thumbs().filter((b) => !b.closest('[aria-hidden="true"]'));
    expect(visible().map((b) => b.getAttribute('aria-label'))).toEqual([
      'Фото 1 из 6',
      'Фото 2 из 6',
      'Фото 3 из 6',
      'Фото 4 из 6',
    ]);
    const arrow = screen.getByRole('button', { name: 'Следующие фото' });
    fireEvent.click(arrow);
    expect(visible()[0]).toHaveAttribute('aria-label', 'Фото 2 из 6');
    fireEvent.click(arrow);
    expect(visible()[0]).toHaveAttribute('aria-label', 'Фото 3 из 6');
    fireEvent.click(arrow);
    expect(visible()[0]).toHaveAttribute('aria-label', 'Фото 1 из 6');
  });

  it('выбрано фото за пределами окна — окно сдвигается к нему', () => {
    const { rerender, onSelectIndex, onOpen } = renderGallery(photos(6), 0);
    rerender(
      <Gallery
        images={photos(6)}
        productName="Диван"
        selectedIndex={5}
        onSelectIndex={onSelectIndex}
        onOpen={onOpen}
      />,
    );
    const visible = thumbs().filter((b) => !b.closest('[aria-hidden="true"]'));
    expect(visible.map((b) => b.getAttribute('aria-label'))).toContain('Фото 6 из 6');
  });

  it('нажатие на главное фото открывает просмотр с этого фото', () => {
    const { onOpen } = renderGallery(photos(3), 2);
    // Первая — главное фото столбика, вторая — слайд ленты
    fireEvent.click(screen.getAllByRole('button', { name: 'Открыть фото 3 на весь экран' })[0]);
    expect(onOpen).toHaveBeenCalledWith(2);
  });
});

describe('галерея товара: лента на узком экране', () => {
  it('точки по числу фото, текущая отмечена', () => {
    renderGallery(photos(4), 2);
    const dots = screen.getByTestId('gallery-dots').children;
    expect(dots).toHaveLength(4);
    expect(dots[2]).toHaveAttribute('data-active', 'true');
  });

  it('прокрутка ленты выбирает фото, на котором остановились', () => {
    const { onSelectIndex } = renderGallery(photos(4));
    const strip = screen.getByTestId('gallery-strip');
    const slide = strip.children[0] as HTMLElement;
    Object.defineProperty(slide, 'offsetWidth', { value: 300, configurable: true });
    Object.defineProperty(strip, 'scrollLeft', { value: 624, configurable: true });
    fireEvent.scroll(strip);
    // 300 + 12 на слайд: 624 — третий
    expect(onSelectIndex).toHaveBeenCalledWith(2);
  });

  it('нажатие на слайд открывает просмотр', () => {
    const { onOpen } = renderGallery(photos(3));
    fireEvent.click(within(screen.getByTestId('gallery-strip')).getAllByRole('button')[1]);
    expect(onOpen).toHaveBeenCalledWith(1);
  });
});

describe('галерея товара: мало фото', () => {
  it('одно фото — без превью и точек', () => {
    renderGallery(photos(1));
    expect(screen.queryByRole('list', { name: 'Фото товара' })).not.toBeInTheDocument();
    expect(screen.queryByTestId('gallery-dots')).not.toBeInTheDocument();
  });

  it('фото нет — заглушка', () => {
    renderGallery([]);
    expect(screen.getByText('Фото скоро появится')).toBeInTheDocument();
  });
});
