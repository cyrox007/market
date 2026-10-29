import { useEffect, useState } from 'react';
import { api } from '../lib/api';

type Node = { externalId: string; label: string; name: string };
function SearchLevel({ level, parent, label, onSelect }: {
  level: 'localities' | 'streets' | 'buildings'; parent: string | null; label: string; onSelect: (node: Node) => void;
}) {
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState<Node[]>([]);
  const [error, setError] = useState('');
  useEffect(() => {
    if (!parent && query.length < 2) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setError('');
      api.addressDirectory.options(level, parent, query, controller.signal)
        .then((response) => { if (!controller.signal.aborted) setOptions(response.data); })
        .catch(() => { if (!controller.signal.aborted) { setOptions([]); setError('Справочник недоступен. Измените запрос для повтора.'); } });
    }, 350);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [level, parent, query]);
  return <label className="block mb-4">{label}
    <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Поиск в справочнике" className="block w-full border rounded-lg p-3" />
    <select aria-label={label} value="" onChange={(e) => { const node = options.find((n) => n.externalId === e.target.value); if (node) onSelect(node); }} className="block w-full border rounded-lg p-3">
      <option value="">Выберите из результатов</option>{options.map((n) => <option key={n.externalId} value={n.externalId}>{n.label || n.name}</option>)}
    </select>{error && <span role="alert">{error}</span>}
  </label>;
}

export default function CheckoutAddress({ onChange }: { onChange: (id: string | null) => void }) {
  const [city, setCity] = useState<Node | null>(null);
  const [street, setStreet] = useState<Node | null>(null);
  const [house, setHouse] = useState<Node | null>(null);
  return <div>
    <SearchLevel level="localities" parent={null} label="Населённый пункт" onSelect={(n) => { setCity(n); setStreet(null); setHouse(null); onChange(null); }} />
    {city && <p>{city.label}</p>}
    {city && <SearchLevel key={city.externalId} level="streets" parent={city.externalId} label="Улица (если есть)" onSelect={(n) => { setStreet(n); setHouse(null); onChange(null); }} />}
    {street && <p>{street.label} <button type="button" onClick={() => { setStreet(null); setHouse(null); onChange(null); }}>Без улицы</button></p>}
    {city && <SearchLevel key={street?.externalId || city.externalId} level="buildings" parent={street?.externalId || city.externalId} label="Дом / корпус / строение" onSelect={(n) => { setHouse(n); onChange(n.externalId); }} />}
    {house && <p role="status">Выбран дом: {house.label}</p>}
  </div>;
}
