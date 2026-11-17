import { useEffect, useId, useRef, useState } from 'react';
import { api, type Address } from '../lib/api';
import Input from './ui/primitives/Input';
import Button from './ui/primitives/Button';
import chevron from './ui/primitives/address-chevron-down.svg';

type Node = { externalId: string; label: string; name: string };
function DirectorySearch({
  label,
  level,
  parent,
  onSelect,
}: {
  label: string;
  level: 'localities' | 'streets' | 'buildings';
  parent: string | null;
  onSelect: (node: Node) => void;
}) {
  const id = useId();
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState<Node[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);
  const [searched, setSearched] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const input = useRef<HTMLInputElement>(null);
  const ready = query.trim().length >= (level === 'buildings' ? 1 : 2);
  useEffect(() => {
    if (!ready) return;
    const controller = new AbortController();
    const timer = setTimeout(() => {
      setLoading(true);
      setError(false);
      setSearched(false);
      const request =
        level === 'localities'
          ? api.localities.search(query.trim(), controller.signal)
          : api.addressDirectory.options(level, parent, query.trim(), controller.signal);
      request
        .then((result) => {
          if (!controller.signal.aborted) {
            setOptions(result.data);
            setSearched(true);
          }
        })
        .catch(() => {
          if (!controller.signal.aborted) {
            setOptions([]);
            setError(true);
          }
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 350);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [level, parent, query, ready, attempt]);
  function select(node: Node) {
    setOpen(false);
    setOptions([]);
    onSelect(node);
  }
  return (
    <div className="relative min-w-0">
      <label htmlFor={id} className="mb-2 block text-14 text-ink-secondary">
        {label}
      </label>
      <Input
        ref={input}
        id={id}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={open && options.length > 0}
        rightSlot={
          level === 'localities' ? (
            <img src={chevron} alt="" width={24} height={24} className="mr-3" />
          ) : undefined
        }
        aria-controls={`${id}-results`}
        aria-activedescendant={
          open && active >= 0 && options[active] ? `${id}-${active}` : undefined
        }
        inputSize="field"
        shape="rounded"
        tone="outline"
        autoComplete="off"
        value={query}
        placeholder={
          level === 'buildings' ? 'Номер дома' : level === 'localities' ? 'Введите город' : 'Улица'
        }
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
        onChange={(e) => {
          setQuery(e.target.value);
          setOptions([]);
          setError(false);
          setSearched(false);
          setLoading(false);
          setActive(-1);
          setOpen(true);
        }}
        onKeyDown={(e) => {
          if (e.key === 'Escape' && open) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(false);
          }
          if (e.key === 'ArrowDown' && options.length) {
            e.preventDefault();
            setOpen(true);
            setActive((index) => (index + 1) % options.length);
          }
          if (e.key === 'ArrowUp' && options.length) {
            e.preventDefault();
            setActive((index) => (index <= 0 ? options.length : index) - 1);
          }
          if (e.key === 'Enter' && open && active >= 0 && options[active]) {
            e.preventDefault();
            select(options[active]);
          }
        }}
      />
      {loading && (
        <p role="status" className="mt-2 text-12 text-ink-secondary">
          Поиск…
        </p>
      )}
      {error && (
        <div role="alert" className="mt-2 text-12 text-brand-red">
          Справочник временно недоступен.{' '}
          <button
            type="button"
            className="underline"
            onClick={() => {
              setAttempt((n) => n + 1);
              setOpen(true);
            }}
          >
            Повторить поиск
          </button>
        </div>
      )}
      {searched && !options.length && (
        <p className="mt-2 text-12 text-ink-secondary">Ничего не найдено. Уточните запрос.</p>
      )}
      {open && options.length > 0 && (
        <div
          id={`${id}-results`}
          role="listbox"
          aria-label={`Результаты: ${label}`}
          className="relative z-10 mt-2 max-h-48 overflow-y-auto rounded-popover bg-surface p-2 shadow-[0_3px_15px_rgba(20,20,20,0.15)]"
        >
          {options.map((node, index) => (
            <button
              key={node.externalId}
              id={`${id}-${index}`}
              type="button"
              role="option"
              tabIndex={-1}
              aria-selected={active === index}
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => select(node)}
              className={`block w-full rounded-btn p-3 text-left text-14 [overflow-wrap:anywhere] ${active === index ? 'bg-surface-grey' : ''}`}
            >
              {node.label || node.name}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

export default function AddressDirectoryFields({
  initialAddress,
  onChange,
}: {
  initialAddress?: Address | null;
  onChange: (id: string | null) => void;
}) {
  const container = useRef<HTMLDivElement>(null);
  const focusNext = useRef(false);
  const [changing, setChanging] = useState(!initialAddress);
  const [city, setCity] = useState<Node | null>(null);
  const [street, setStreet] = useState<Node | null>(null);
  const [noStreet, setNoStreet] = useState(false);
  const [house, setHouse] = useState<Node | null>(null);
  useEffect(() => {
    if (!focusNext.current) return;
    focusNext.current = false;
    (
      container.current?.querySelector<HTMLInputElement>('input:not([readonly])') ||
      container.current
    )?.focus();
  }, [changing, city, street, noStreet, house]);
  if (!changing && initialAddress)
    return (
      <div ref={container} tabIndex={-1} className="flex flex-col gap-4 outline-none">
        <div>
          <label htmlFor="address-city-saved" className="mb-2 block text-14 text-ink-secondary">
            Город
          </label>
          <Input
            id="address-city-saved"
            inputSize="field"
            shape="rounded"
            tone="outline"
            value={initialAddress.city || 'Сохранённый населённый пункт'}
            rightSlot={<img src={chevron} alt="" width={24} height={24} className="mr-3" />}
            readOnly
          />
        </div>
        <div>
          <label htmlFor="address-street-saved" className="mb-2 block text-14 text-ink-secondary">
            Улица и дом
          </label>
          <Input
            id="address-street-saved"
            inputSize="field"
            shape="rounded"
            tone="outline"
            value={
              [initialAddress.street, initialAddress.house].filter(Boolean).join(', ') ||
              initialAddress.full_address ||
              'Сохранённый адрес'
            }
            readOnly
          />
        </div>
        <Button
          type="button"
          variant="ghost"
          size="xs"
          onClick={() => {
            focusNext.current = true;
            setChanging(true);
            onChange(null);
          }}
        >
          Изменить город, улицу или дом
        </Button>
      </div>
    );
  return (
    <div ref={container} tabIndex={-1} className="flex flex-col gap-4 outline-none">
      {city ? (
        <div className="text-14">
          <span className="block text-ink-secondary">Город</span>
          <p className="mt-2 [overflow-wrap:anywhere]">{city.label || city.name}</p>
          <button
            type="button"
            className="mt-2 underline"
            onClick={() => {
              focusNext.current = true;
              setCity(null);
              setStreet(null);
              setHouse(null);
              setNoStreet(false);
              onChange(null);
            }}
          >
            Изменить город
          </button>
        </div>
      ) : (
        <DirectorySearch
          label="Город"
          level="localities"
          parent={null}
          onSelect={(node) => {
            focusNext.current = true;
            setCity(node);
            onChange(null);
          }}
        />
      )}
      {city && !street && !noStreet && (
        <>
          <DirectorySearch
            key={city.externalId}
            label="Улица и дом"
            level="streets"
            parent={city.externalId}
            onSelect={(node) => {
              focusNext.current = true;
              setStreet(node);
              setHouse(null);
              onChange(null);
            }}
          />
          <button
            type="button"
            className="self-start text-14 underline"
            onClick={() => {
              focusNext.current = true;
              setNoStreet(true);
              onChange(null);
            }}
          >
            Адрес без улицы
          </button>
        </>
      )}
      {city && (street || noStreet) && (
        <>
          <div className="text-14">
            <span>{street?.label || 'Адрес без улицы'}</span>{' '}
            <button
              type="button"
              className="underline"
              onClick={() => {
                focusNext.current = true;
                setStreet(null);
                setNoStreet(false);
                setHouse(null);
                onChange(null);
              }}
            >
              Изменить улицу
            </button>
          </div>
          {house ? (
            <div className="text-14">
              <p role="status">Выбран дом: {house.label || house.name}</p>
              <button
                type="button"
                className="mt-2 underline"
                onClick={() => {
                  focusNext.current = true;
                  setHouse(null);
                  onChange(null);
                }}
              >
                Изменить дом
              </button>
            </div>
          ) : (
            <DirectorySearch
              key={street?.externalId || city.externalId}
              label="Дом / корпус / строение"
              level="buildings"
              parent={street?.externalId || city.externalId}
              onSelect={(node) => {
                focusNext.current = true;
                setHouse(node);
                onChange(node.externalId);
              }}
            />
          )}
        </>
      )}
    </div>
  );
}
