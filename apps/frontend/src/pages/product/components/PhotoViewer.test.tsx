import { useState } from 'react';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import PhotoViewer from './PhotoViewer';

const IMAGES = ['/p1.jpg', '/p2.jpg', '/p3.jpg'];

/** Как на странице: выбранное фото общее у галереи и просмотра */
function Harness({ start = 1 }: { start?: number }) {
  const [index, setIndex] = useState(start);
  const [open, setOpen] = useState(false);
  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>
        Открыть
      </button>
      <p data-testid="page-photo">{index}</p>
      <PhotoViewer
        open={open}
        images={IMAGES}
        productName="Диван"
        index={index}
        onIndexChange={setIndex}
        onClose={() => setOpen(false)}
      />
    </>
  );
}

const openViewer = () => {
  const opener = screen.getByRole('button', { name: 'Открыть' });
  opener.focus();
  fireEvent.click(opener);
  return { dialog: screen.getByRole('dialog', { name: 'Фото товара' }), opener };
};

const mainPhoto = () => within(screen.getByRole('dialog')).getByRole('img', { name: /Диван/ });

describe('просмотр фото на весь экран', () => {
  it('открывается на том фото, что выбрано на странице', () => {
    render(<Harness start={1} />);
    openViewer();
    expect(mainPhoto()).toHaveAttribute('src', '/p2.jpg');
    expect(screen.getByRole('button', { name: 'Фото 2 из 3' })).toHaveAttribute(
      'aria-current',
      'true',
    );
  });

  it('стрелки листают по кругу', () => {
    render(<Harness start={2} />);
    openViewer();
    fireEvent.click(screen.getByRole('button', { name: 'Следующее фото' }));
    expect(mainPhoto()).toHaveAttribute('src', '/p1.jpg');
    fireEvent.click(screen.getByRole('button', { name: 'Предыдущее фото' }));
    expect(mainPhoto()).toHaveAttribute('src', '/p3.jpg');
  });

  it('клавиши ← → листают', () => {
    render(<Harness start={0} />);
    openViewer();
    fireEvent.keyDown(document, { key: 'ArrowRight' });
    expect(mainPhoto()).toHaveAttribute('src', '/p2.jpg');
    fireEvent.keyDown(document, { key: 'ArrowLeft' });
    expect(mainPhoto()).toHaveAttribute('src', '/p1.jpg');
  });

  it('свайп влево — следующее, вправо — предыдущее', () => {
    render(<Harness start={1} />);
    openViewer();
    const stage = screen.getByTestId('viewer-stage');
    fireEvent.pointerDown(stage, { clientX: 300, pointerId: 1 });
    fireEvent.pointerUp(stage, { clientX: 200, pointerId: 1 });
    expect(mainPhoto()).toHaveAttribute('src', '/p3.jpg');
    fireEvent.pointerDown(stage, { clientX: 200, pointerId: 2 });
    fireEvent.pointerUp(stage, { clientX: 300, pointerId: 2 });
    expect(mainPhoto()).toHaveAttribute('src', '/p2.jpg');
    // Короткое движение — не свайп
    fireEvent.pointerDown(stage, { clientX: 200, pointerId: 3 });
    fireEvent.pointerUp(stage, { clientX: 220, pointerId: 3 });
    expect(mainPhoto()).toHaveAttribute('src', '/p2.jpg');
  });

  it('превью выбирает фото', () => {
    render(<Harness start={0} />);
    openViewer();
    fireEvent.click(screen.getByRole('button', { name: 'Фото 3 из 3' }));
    expect(mainPhoto()).toHaveAttribute('src', '/p3.jpg');
  });

  it('крестик закрывает; на странице — последнее просмотренное, фокус на кнопке', async () => {
    render(<Harness start={0} />);
    const { opener } = openViewer();
    fireEvent.click(screen.getByRole('button', { name: 'Следующее фото' }));
    fireEvent.click(screen.getByRole('button', { name: 'Закрыть' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(screen.getByTestId('page-photo')).toHaveTextContent('1');
    expect(opener).toHaveFocus();
  });

  it('Esc закрывает', async () => {
    render(<Harness />);
    openViewer();
    fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('«Назад» закрывает и не уводит со страницы', async () => {
    const before = window.location.href;
    render(<Harness />);
    openViewer();
    window.history.back();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(window.location.href).toBe(before);
  });

  it('фото — по своим пропорциям, а не на весь квадрат (скругление по углам фото)', () => {
    render(<Harness start={0} />);
    openViewer();
    const img = mainPhoto();
    Object.defineProperty(img, 'naturalWidth', { value: 600 });
    Object.defineProperty(img, 'naturalHeight', { value: 900 });
    fireEvent.load(img);
    expect(img.style.aspectRatio).toBe(String(600 / 900));
    expect(img.style.width).toBe(`${(600 / 900) * 100}%`);
  });

  it('одно фото — без стрелок и превью', () => {
    function One() {
      return (
        <PhotoViewer
          open
          images={['/only.jpg']}
          productName="Диван"
          index={0}
          onIndexChange={() => {}}
          onClose={() => {}}
        />
      );
    }
    render(<One />);
    expect(screen.queryByRole('button', { name: 'Следующее фото' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /из 1/ })).not.toBeInTheDocument();
  });
});
