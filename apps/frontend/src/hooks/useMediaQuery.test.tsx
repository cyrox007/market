import { renderHook } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { beforeAll, describe, expect, it } from 'vitest';
import { useMediaMatch } from './useMediaQuery';

beforeAll(() => {
  window.matchMedia = ((query: string) => ({
    matches: query === '(max-width: 979.98px)',
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

function Probe() {
  const match = useMediaMatch('(max-width: 979.98px)');
  return <>{String(match)}</>;
}

describe('ширина экрана', () => {
  it('на сервере неизвестна — null, чтобы разметка не угадывала', () => {
    expect(renderToString(<Probe />)).toBe('null');
  });

  it('в браузере после первого кадра — совпал ли запрос', () => {
    const { result } = renderHook(() => useMediaMatch('(max-width: 979.98px)'));
    expect(result.current).toBe(true);
    const other = renderHook(() => useMediaMatch('(min-width: 1px)'));
    expect(other.result.current).toBe(false);
  });
});
