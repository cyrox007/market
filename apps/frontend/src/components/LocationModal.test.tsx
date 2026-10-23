import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import LocationModal from './LocationModal';
import { api } from '../lib/api';

const { selectLocality, saved } = vi.hoisted(() => ({
  selectLocality: vi.fn(),
  saved: { locality: null as null | { externalId: string; name: string; label: string; source: string } },
}));
vi.mock('../hooks/useRegion', () => ({ useRegion: () => ({ locality: saved.locality, selectLocality }) }));
vi.mock('../lib/api', () => ({
  api: {
    localities: { detect: vi.fn(), get: vi.fn() },
    addressDirectory: { regions: vi.fn(), localities: vi.fn() },
  },
}));
const city = { externalId: '01234567-1234-1234-1234-012345678901', name: 'Липецк', label: 'обл. Липецкая, г. Липецк', source: 'gar' };
const lipetskaya = { externalId: '1490490e-49c5-421c-9572-5673ba5d80c8', name: 'Липецкая', label: 'обл. Липецкая' };
const regions = [
  { externalId: 'd8327a56-80de-4df2-815c-4f6ab1224c50', name: 'Адыгея', label: 'респ. Адыгея' },
  lipetskaya,
  { externalId: '29251dcf-00a1-4e34-98d4-5c47484a36d4', name: 'Московская', label: 'обл. Московская' },
];

beforeEach(() => {
  vi.clearAllMocks();
  saved.locality = null;
  vi.mocked(api.addressDirectory.regions).mockResolvedValue({ data: regions });
});

/** Регион — первым шагом: пункты ищем только внутри него */
async function pickRegion(name = 'обл. Липецкая') {
  fireEvent.change(await screen.findByLabelText('Регион'), { target: { value: 'Лип' } });
  fireEvent.click(await screen.findByRole('button', { name }));
}
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
  it('пункт ищется только после выбора региона', async () => {
    render(<LocationModal isOpen onClose={vi.fn()} />);
    expect(await screen.findByLabelText('Населённый пункт')).toBeDisabled();
    fireEvent.change(screen.getByLabelText('Регион'), { target: { value: 'лип' } });
    expect(screen.getByRole('button', { name: 'обл. Липецкая' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'респ. Адыгея' })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'обл. Липецкая' }));
    expect(screen.getByLabelText('Населённый пункт')).toBeEnabled();
    expect(screen.getByText('обл. Липецкая')).toBeInTheDocument();
  });

  it('регион текущего пункта — по regionExternalId из справочника', async () => {
    saved.locality = { ...city, label: 'Липецк', regionExternalId: lipetskaya.externalId } as typeof city;
    render(<LocationModal isOpen onClose={vi.fn()} />);
    expect(await screen.findByRole('button', { name: 'Изменить регион' })).toBeInTheDocument();
    expect(screen.getByText('обл. Липецкая')).toBeInTheDocument();
  });

  it('регион текущего пункта подставляется сам', async () => {
    saved.locality = city;
    render(<LocationModal isOpen onClose={vi.fn()} />);
    expect(await screen.findByLabelText('Населённый пункт')).toBeEnabled();
    expect(screen.getByRole('button', { name: 'Изменить регион' })).toBeInTheDocument();
  });

  it('регионы не загрузились — сообщение и повтор', async () => {
    vi.mocked(api.addressDirectory.regions).mockRejectedValueOnce(new Error('503'));
    render(<LocationModal isOpen onClose={vi.fn()} />);
    expect(await screen.findByRole('alert')).toHaveTextContent('Не удалось загрузить регионы');
    fireEvent.click(screen.getByRole('button', { name: 'Повторить' }));
    expect(await screen.findByLabelText('Регион')).toBeInTheDocument();
  });

  it('searches the classifier and saves a locality even without a warehouse location', async () => {
    vi.mocked(api.addressDirectory.localities).mockResolvedValue({ data: [city] });
    vi.mocked(api.localities.get).mockResolvedValue({ data: city, shippingLocation: null });
    const close = vi.fn();
    render(<LocationModal isOpen onClose={close} />);
    await pickRegion();
    fireEvent.change(screen.getByLabelText('Населённый пункт'), { target: { value: 'Лип' } });
    fireEvent.click(await screen.findByRole('button', { name: /г\. Липецк/ }));
    expect(api.addressDirectory.localities).toHaveBeenCalledWith(
      { parentExternalId: lipetskaya.externalId, q: 'Лип', limit: 50 },
      expect.any(AbortSignal),
    );
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
    vi.mocked(api.addressDirectory.localities).mockResolvedValue({ data: [city] });
    vi.mocked(api.localities.get).mockRejectedValue(new Error('unavailable'));
    render(<LocationModal isOpen onClose={vi.fn()} />);
    await pickRegion();
    fireEvent.change(screen.getByLabelText('Населённый пункт'), { target: { value: 'Липецк' } });
    fireEvent.click(await screen.findByRole('button', { name: /г\. Липецк/ }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Выбор не изменён');
    expect(selectLocality).not.toHaveBeenCalled();
  });
  it('shows a cooldown for geo 429 while keeping manual search available', async () => {
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: {
      getCurrentPosition: vi.fn((success) => success({ coords: { latitude: 52.6, longitude: 39.6 } })),
    } });
    vi.mocked(api.localities.detect).mockRejectedValue({ status: 429, retryAfter: 20 });
    vi.mocked(api.addressDirectory.localities).mockResolvedValue({ data: [city] });
    render(<LocationModal isOpen onClose={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Определить моё местоположение' }));
    expect(await screen.findByRole('button', { name: 'Повторить через 20 с' })).toBeDisabled();
    expect(screen.queryByText(/справочник недоступен/)).not.toBeInTheDocument();
    await pickRegion();
    fireEvent.change(screen.getByLabelText('Населённый пункт'), { target: { value: 'Липецк' } });
    expect(await screen.findByRole('button', { name: /г\. Липецк/ })).toBeInTheDocument();
  });
});
