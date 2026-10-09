import { useEffect, useRef, useState } from 'react';
import { Search, X } from 'lucide-react';
import { useRegion } from '../hooks/useRegion';
import { api, type CustomerLocality } from '../lib/api';

interface LocationModalProps { isOpen: boolean; onClose: () => void; isFirstVisit?: boolean }

export default function LocationModal({ isOpen, onClose, isFirstVisit = false }: LocationModalProps) {
  const { locality, selectLocality } = useRegion();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<CustomerLocality[]>([]);
  const [candidates, setCandidates] = useState<CustomerLocality[]>([]);
  const [searching, setSearching] = useState(false);
  const [detecting, setDetecting] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [geoMessage, setGeoMessage] = useState('');
  const [geoWait, setGeoWait] = useState(0);
  const [searchWait, setSearchWait] = useState(0);
  const searchPaused = searchWait > 0;
  useEffect(() => {
    if (!geoWait && !searchWait) return;
    const timer = setInterval(() => {
      setGeoWait((value) => Math.max(0, value - 1));
      setSearchWait((value) => Math.max(0, value - 1));
    }, 1000);
    return () => clearInterval(timer);
  }, [geoWait, searchWait]);
  const geoRequest = useRef<AbortController | null>(null);
  const autoAttempted = useRef(false);
  const active = useRef(false);
  const closeHandler = useRef(onClose);
  useEffect(() => { closeHandler.current = onClose; }, [onClose]);

  async function detect() {
    if (geoWait || detecting) return;
    if (!navigator.geolocation) {
      setGeoMessage('Браузер не поддерживает геолокацию. Используйте поиск.');
      return;
    }
    geoRequest.current?.abort();
    const controller = new AbortController();
    geoRequest.current = controller;
    setDetecting(true);
    setCandidates([]);
    setGeoMessage('Получаем координаты браузера…');
    navigator.geolocation.getCurrentPosition(async ({ coords }) => {
      if (controller.signal.aborted) return;
      setGeoMessage('Координаты получены. Ищем ближайшие населённые пункты…');
      try {
        const response = await api.localities.detect(coords.latitude, coords.longitude, controller.signal);
        if (controller.signal.aborted) return;
        setCandidates(response.data);
        setGeoMessage(response.data.length ? 'Ближайшие населённые пункты. Подтвердите ваш:' : 'Координаты получены, но населённый пункт не найден. Используйте поиск.');
      } catch (failure) {
        if (controller.signal.aborted) return;
        const problem = failure as { status?: number; retryAfter?: number };
        if (problem.status === 429) {
          setGeoWait(problem.retryAfter || 60);
          setGeoMessage('Координаты получены. Слишком частые попытки определения — подождите или используйте поиск.');
        } else setGeoMessage('Координаты получены, но справочник недоступен. Попробуйте позже или используйте поиск.');
      } finally {
        if (!controller.signal.aborted) setDetecting(false);
      }
    }, (failure) => {
      if (controller.signal.aborted) return;
      setDetecting(false);
      setGeoMessage(failure.code === 1 ? 'Доступ к геолокации запрещён. Разрешите его в браузере или используйте поиск.' : 'Не удалось получить координаты. Попробуйте ещё раз или используйте поиск.');
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
  }

  useEffect(() => {
    active.current = isOpen;
    if (!isOpen) return;
    setDetecting(false);
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const escape = (event: KeyboardEvent) => { if (event.key === 'Escape') closeHandler.current(); };
    window.addEventListener('keydown', escape);
    return () => {
      active.current = false;
      document.body.style.overflow = previous;
      window.removeEventListener('keydown', escape);
      geoRequest.current?.abort();
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen || !isFirstVisit || locality || autoAttempted.current) return;
    const timer = setTimeout(() => { autoAttempted.current = true; void detect(); }, 0);
    return () => clearTimeout(timer);
    // One automatic attempt; explicit button allows retries.
  }, [isOpen, isFirstVisit, locality]);

  useEffect(() => {
    if (!isOpen) return;
    setResults([]);
    setError('');
    if (query.trim().length < 2 || searchPaused) { setSearching(false); return; }
    const controller = new AbortController();
    setSearching(true);
    const timer = setTimeout(async () => {
      try {
        const response = await api.localities.search(query.trim(), controller.signal);
        if (!controller.signal.aborted) setResults(response.data.filter((item) => /^(?:\d{13}|[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})$/i.test(item.externalId)));
      } catch (failure) {
        if (controller.signal.aborted) return;
        const problem = failure as { status?: number; retryAfter?: number };
        if (problem.status === 429) setSearchWait(problem.retryAfter || 60);
        else setError('Не удалось загрузить населённые пункты. Измените запрос или попробуйте позже.');
      } finally {
        if (!controller.signal.aborted) setSearching(false);
      }
    }, 400);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [isOpen, query, searchPaused]);

  async function choose(item: CustomerLocality) {
    if (saving || searchPaused) return;
    setSaving(true);
    setError('');
    try {
      const response = await api.localities.get(item.externalId);
      if (!active.current) return;
      selectLocality(response.data, response.shippingLocation);
      onClose();
    } catch (failure) {
      if (!active.current) return;
      const problem = failure as { status?: number; retryAfter?: number };
      if (problem.status === 429) {
        setSearchWait(problem.retryAfter || 60);
        setError('Слишком частые запросы. Дождитесь окончания паузы и подтвердите выбор ещё раз.');
      } else setError('Не удалось подтвердить населённый пункт в справочнике. Выбор не изменён.');
    } finally { setSaving(false); }
  }

  if (!isOpen) return null;
  const rows = query.trim().length >= 2 ? results : candidates;
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
      <div role="dialog" aria-modal="true" aria-labelledby="locality-title" className="relative w-full max-w-2xl rounded-2xl bg-white p-8 max-h-[85vh] overflow-y-auto" onClick={(event) => event.stopPropagation()}>
        <button aria-label="Закрыть" onClick={onClose} className="absolute right-6 top-6 text-gray-400"><X /></button>
        <h2 id="locality-title" className="mb-2 pr-8 text-2xl font-bold">Выберите город доставки</h2>
        <p className="mb-5 text-sm text-gray-600">Найдите ваш город, посёлок или село в адресном справочнике.</p>
        <div className="relative mb-4">
          <Search className="absolute left-3 top-3 text-gray-400" size={20} />
          <input aria-label="Населённый пункт" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Введите минимум 2 буквы…" className="w-full rounded-lg border border-gray-300 py-3 pl-10 pr-4 text-sm" autoFocus />
        </div>
        <button disabled={detecting || saving || geoWait > 0} onClick={() => void detect()} className="mb-3 rounded-lg border border-gray-300 px-4 py-2 text-sm disabled:opacity-50">{geoWait ? `Повторить через ${geoWait} с` : detecting ? 'Определяем…' : locality ? 'Определить заново' : 'Определить моё местоположение'}</button>
        {searchWait > 0 && <p role="status" className="mb-3 text-sm text-gray-600">Поиск можно повторить через {searchWait} с. Запрос сохранён.</p>}
        <p role="status" className="mb-3 text-sm text-gray-600">{geoMessage}</p>
        {error && <p role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-600">{error}</p>}
        {searching ? <p role="status">Ищем населённые пункты…</p> : (
          <div className="space-y-2">
            {rows.map((item) => <button key={item.externalId} disabled={saving} onClick={() => void choose(item)} className="w-full rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 disabled:opacity-50"><span className="block font-medium">{item.name}</span><span className="text-sm text-gray-500">{item.label}</span></button>)}
            {query.trim().length >= 2 && !rows.length && !error && !searchWait && <p className="text-sm text-gray-500">Ничего не найдено. Уточните название.</p>}
          </div>
        )}
        {saving && <p role="status" className="mt-3 text-sm">Сохраняем выбор…</p>}
        {locality && <p className="mt-5 border-t pt-4 text-sm text-gray-600">Текущий выбор: {locality.label || locality.name}</p>}
        <button onClick={onClose} className="mt-5 text-sm text-gray-500">Выбрать позже</button>
      </div>
    </div>
  );
}
