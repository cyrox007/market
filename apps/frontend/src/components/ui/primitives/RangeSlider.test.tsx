import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import RangeSlider from './RangeSlider';

const setup = (value: [number, number], extra: { step?: number } = {}) => {
  const onChange = vi.fn();
  const onChangeEnd = vi.fn();
  const utils = render(
    <RangeSlider
      label="Цена"
      min={0}
      max={1000}
      step={extra.step ?? 10}
      value={value}
      onChange={onChange}
      onChangeEnd={onChangeEnd}
    />,
  );
  return {
    ...utils,
    low: screen.getByRole('slider', { name: 'Цена от' }),
    high: screen.getByRole('slider', { name: 'Цена до' }),
    onChange,
    onChangeEnd,
  };
};

describe('RangeSlider', () => {
  it('exposes two sliders bounded by each other', () => {
    const { low, high } = setup([200, 800]);
    expect(low).toHaveAttribute('aria-valuemin', '0');
    expect(low).toHaveAttribute('aria-valuemax', '800');
    expect(low).toHaveAttribute('aria-valuenow', '200');
    expect(high).toHaveAttribute('aria-valuemin', '200');
    expect(high).toHaveAttribute('aria-valuemax', '1000');
    expect(high).toHaveAttribute('aria-valuenow', '800');
  });

  it('reads values with digit groups', () => {
    render(
      <RangeSlider
        label="Цена"
        min={890}
        max={180350}
        value={[23500, 180350]}
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('slider', { name: 'Цена от' })).toHaveAttribute(
      'aria-valuetext',
      '23 500',
    );
  });

  it('moves by a step with arrows and by ten steps with Page keys', () => {
    const { low, high, onChange, onChangeEnd } = setup([200, 800]);

    fireEvent.keyDown(low, { key: 'ArrowRight' });
    expect(onChange).toHaveBeenLastCalledWith([210, 800]);
    expect(onChangeEnd).toHaveBeenLastCalledWith([210, 800]);

    fireEvent.keyDown(low, { key: 'ArrowDown' });
    expect(onChange).toHaveBeenLastCalledWith([190, 800]);

    fireEvent.keyDown(high, { key: 'PageDown' });
    expect(onChange).toHaveBeenLastCalledWith([200, 700]);

    fireEvent.keyDown(high, { key: 'ArrowUp' });
    expect(onChange).toHaveBeenLastCalledWith([200, 810]);
  });

  it('jumps to the ends with Home and End without crossing the other thumb', () => {
    const { low, high, onChange } = setup([200, 800]);

    fireEvent.keyDown(low, { key: 'Home' });
    expect(onChange).toHaveBeenLastCalledWith([0, 800]);

    fireEvent.keyDown(low, { key: 'End' });
    expect(onChange).toHaveBeenLastCalledWith([800, 800]);

    fireEvent.keyDown(high, { key: 'End' });
    expect(onChange).toHaveBeenLastCalledWith([200, 1000]);

    fireEvent.keyDown(high, { key: 'Home' });
    expect(onChange).toHaveBeenLastCalledWith([200, 200]);
  });

  it('stays put at a limit', () => {
    const { low, high, onChange } = setup([0, 1000]);
    fireEvent.keyDown(low, { key: 'ArrowLeft' });
    fireEvent.keyDown(high, { key: 'ArrowRight' });
    expect(onChange).not.toHaveBeenCalled();
  });

  it('does not let thumbs pass each other', () => {
    const { low, onChange } = setup([500, 500]);
    fireEvent.keyDown(low, { key: 'ArrowRight' });
    expect(onChange).not.toHaveBeenCalled();
  });

  it('moves the nearest thumb when the track is pressed and dragged', () => {
    const { container, onChange, onChangeEnd } = setup([100, 900], { step: 1 });
    const track = container.firstElementChild as HTMLElement;
    // 216 px = 200 px хода + по 8 px на половинки бегунков по краям
    track.getBoundingClientRect = () =>
      ({ left: 0, width: 216, top: 0, height: 16, right: 216, bottom: 16 }) as DOMRect;

    fireEvent.pointerDown(track, { clientX: 168, pointerId: 1 });
    expect(onChange).toHaveBeenLastCalledWith([100, 800]);

    fireEvent.pointerMove(track, { clientX: 148, pointerId: 1 });
    expect(onChange).toHaveBeenLastCalledWith([100, 700]);
    expect(onChangeEnd).not.toHaveBeenCalled();

    fireEvent.pointerUp(track, { clientX: 148, pointerId: 1 });
    expect(onChangeEnd).toHaveBeenCalledWith([100, 700]);

    fireEvent.pointerDown(track, { clientX: 48, pointerId: 2 });
    expect(onChange).toHaveBeenLastCalledWith([200, 900]);
  });

  it('шаг кратен step, даже если минимум не круглый: 18 243 → 18 300 → 18 400', () => {
    const onChange = vi.fn();
    const { rerender } = render(
      <RangeSlider
        label="Цена"
        min={18243}
        max={180350}
        step={100}
        value={[18243, 180350]}
        onChange={onChange}
      />,
    );
    fireEvent.keyDown(screen.getByRole('slider', { name: 'Цена от' }), { key: 'ArrowRight' });
    expect(onChange).toHaveBeenLastCalledWith([18300, 180350]);

    rerender(
      <RangeSlider
        label="Цена"
        min={18243}
        max={180350}
        step={100}
        value={[18300, 180350]}
        onChange={onChange}
      />,
    );
    fireEvent.keyDown(screen.getByRole('slider', { name: 'Цена от' }), { key: 'ArrowRight' });
    expect(onChange).toHaveBeenLastCalledWith([18400, 180350]);
  });
});
