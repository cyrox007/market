import { describe, expect, it } from 'vitest';
import { sliderDescription } from './home-sliders';

describe('sliderDescription', () => {
  it('keeps plain text and line breaks used by promo cards', () => {
    expect(sliderDescription('First line\nSecond line')).toBe('First line\nSecond line');
  });

  it('normalizes legacy RichEditor HTML', () => {
    expect(sliderDescription('<p>First line<br>Second &amp; third</p>')).toBe(
      'First line\nSecond & third',
    );
  });

  it('returns null for empty content', () => {
    expect(sliderDescription(null)).toBeNull();
    expect(sliderDescription('')).toBeNull();
  });
});
