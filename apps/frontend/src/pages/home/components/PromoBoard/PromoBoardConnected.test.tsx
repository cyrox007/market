import type { ReactNode } from 'react';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { SWRConfig } from 'swr';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import PromoBoardConnected from './PromoBoardConnected';

const api = vi.hoisted(() => ({ sliders: { home: vi.fn() } }));
vi.mock('../../../../lib/api', () => ({ api }));

const slide = (id: number, title: string) => ({
  id,
  title,
  description: null,
  link: null,
  button_text: null,
  badge_text: null,
  image: `/storage/${id}.jpg`,
});

function answer(top: { main?: unknown[]; right_top?: unknown; right_bottom?: unknown }) {
  api.sliders.home.mockResolvedValue({
    data: { top: { main: [], right_top: null, right_bottom: null, ...top }, bottom: [] },
  });
}

const wrapper = ({ children }: { children: ReactNode }) => (
  <SWRConfig value={{ provider: () => new Map(), dedupingInterval: 0 }}>
    <MemoryRouter>{children}</MemoryRouter>
  </SWRConfig>
);

beforeAll(() => {
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  })) as unknown as typeof window.matchMedia;
});

beforeEach(() => {
  vi.resetAllMocks();
});

describe('PromoBoardConnected', () => {
  it('shows the demo slider and both demo cards when the admin is empty', async () => {
    answer({});
    render(<PromoBoardConnected />, { wrapper });

    expect((await screen.findAllByText('Территория оптовых цен')).length).toBeGreaterThan(0);
    expect(screen.getByText('Кредит и рассрочка')).toBeInTheDocument();
    expect(screen.getByText('Гарантия честной цены')).toBeInTheDocument();
  });

  it('keeps cards from the admin when there are no slides', async () => {
    answer({ right_top: slide(7, 'Скидки на кухни'), right_bottom: slide(8, 'Матрасы в подарок') });
    render(<PromoBoardConnected />, { wrapper });

    expect(await screen.findByText('Скидки на кухни')).toBeInTheDocument();
    expect(screen.getByText('Матрасы в подарок')).toBeInTheDocument();
    expect(screen.getAllByText('Территория оптовых цен').length).toBeGreaterThan(0);
    expect(screen.queryByText('Кредит и рассрочка')).not.toBeInTheDocument();
  });

  it('fills a missing card with the demo one so the column is never empty', async () => {
    answer({ main: [slide(1, 'Осенняя распродажа')], right_top: slide(7, 'Скидки на кухни') });
    render(<PromoBoardConnected />, { wrapper });

    expect((await screen.findAllByText('Осенняя распродажа')).length).toBeGreaterThan(0);
    expect(screen.getByText('Скидки на кухни')).toBeInTheDocument();
    expect(screen.getByText('Гарантия честной цены')).toBeInTheDocument();
    expect(screen.queryByText('Территория оптовых цен')).not.toBeInTheDocument();
  });
});
