import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LocationModal from './LocationModal';
import { api } from '../lib/api';

const { selectLocality } = vi.hoisted(() => ({ selectLocality: vi.fn() }));
vi.mock('../hooks/useRegion', () => ({ useRegion: () => ({ locality: null, selectLocality }) }));
vi.mock('../lib/api', () => ({ api: { localities: { search: vi.fn(), detect: vi.fn(), get: vi.fn() } } }));
const city = { externalId: '01234567-1234-1234-1234-012345678901', name: 'Липецк', label: 'Липецкая область, город Липецк', source: 'gar' };

beforeEach(() => vi.clearAllMocks());
afterEach(cleanup);

describe('LocationModal locality selection', () => {
  it('does not cancel detection when the parent changes its close callback', async () => {
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
      getCurrentPosition: vi.fn((success) => success({ coords: { latitude: 52.6, longitude: 39.6 } })),
    } });
    let finish!: (value: { data: typeof city[] }) => void;
    vi.mocked(api.localities.detect).mockReturnValue(new Promise((resolve) => { finish = resolve; }));
    const view = render(<LocationModal isOpen onClose={() => undefined} />);
    fireEvent.click(screen.getByRole('button', { name: 'Определить моё местоположение' }));
    const signal = vi.mocked(api.localities.detect).mock.calls[0][2];
    view.rerender(<LocationModal isOpen onClose={() => undefined} />);
    expect(signal?.aborted).toBe(false);
    expect(screen.getByRole('button', { name: 'Определяем…' })).toBeDisabled();
    finish({ data: [city] });
    expect(await screen.findByText('Ближайшие населённые пункты. Подтвердите ваш:')).toBeInTheDocument();
    view.rerender(<LocationModal isOpen={false} onClose={() => undefined} />);
    expect(signal?.aborted).toBe(true);
  });
  it('searches the classifier and saves a locality even without a warehouse location', async () => {
    vi.mocked(api.localities.search).mockResolvedValue({ data: [city] });
    vi.mocked(api.localities.get).mockResolvedValue({ data: city, shippingLocation: null });
    const close = vi.fn();
    render(<LocationModal isOpen onClose={close} />);
    fireEvent.change(screen.getByLabelText('Населённый пункт'), { target: { value: 'Липецк' } });
    fireEvent.click(await screen.findByRole('button', { name: /Липецкая область/ }));
    await waitFor(() => expect(selectLocality).toHaveBeenCalledWith(city, null));
    expect(close).toHaveBeenCalled();
  });

  it('requests nearby settlements and requires explicit confirmation', async () => {
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
      getCurrentPosition: vi.fn((success) => success({ coords: { latitude: 52.6, longitude: 39.6 } })),
    } });
    vi.mocked(api.localities.detect).mockResolvedValue({ data: [city] });
    render(<LocationModal isOpen onClose={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Определить моё местоположение' }));
    expect(await screen.findByText('Ближайшие населённые пункты. Подтвердите ваш:')).toBeInTheDocument();
    expect(api.localities.detect).toHaveBeenCalledWith(52.6, 39.6, expect.any(AbortSignal));
    expect(selectLocality).not.toHaveBeenCalled();
  });

  it('explains denied permission without claiming the coordinates were found', async () => {
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
      getCurrentPosition: vi.fn((_success, failure) => failure({ code: 1 })),
    } });
    render(<LocationModal isOpen onClose={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Определить моё местоположение' }));
    expect(await screen.findByText(/Доступ к геолокации запрещён/)).toBeInTheDocument();
    expect(api.localities.detect).not.toHaveBeenCalled();
  });

  it('keeps the previous choice when confirmation fails', async () => {
    vi.mocked(api.localities.search).mockResolvedValue({ data: [city] });
    vi.mocked(api.localities.get).mockRejectedValue(new Error('unavailable'));
    render(<LocationModal isOpen onClose={vi.fn()} />);
    fireEvent.change(screen.getByLabelText('Населённый пункт'), { target: { value: 'Липецк' } });
    fireEvent.click(await screen.findByRole('button', { name: /Липецкая область/ }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Выбор не изменён');
    expect(selectLocality).not.toHaveBeenCalled();
  });
  it('shows a cooldown for geo 429 while keeping manual search available', async () => {
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
      getCurrentPosition: vi.fn((success) => success({ coords: { latitude: 52.6, longitude: 39.6 } })),
    } });
    vi.mocked(api.localities.detect).mockRejectedValue({ status: 429, retryAfter: 20 });
    vi.mocked(api.localities.search).mockResolvedValue({ data: [city] });
    render(<LocationModal isOpen onClose={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Определить моё местоположение' }));
    expect(await screen.findByRole('button', { name: 'Повторить через 20 с' })).toBeDisabled();
    expect(screen.queryByText(/справочник недоступен/)).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Населённый пункт'), { target: { value: 'Липецк' } });
    expect(await screen.findByRole('button', { name: /Липецкая область/ })).toBeInTheDocument();
  });
});
