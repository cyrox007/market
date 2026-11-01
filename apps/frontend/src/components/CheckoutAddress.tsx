import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { api } from '../lib/api';
import Input from './ui/primitives/Input';

export type DirectoryNode = { externalId: string; name: string; label: string };
export type DirectorySelection = {
  city: DirectoryNode;
  street: DirectoryNode | null;
  house: DirectoryNode;
};

function SearchLevel({
  level,
  parent,
  label,
  value,
  onEdit,
  onSelect,
}: {
  level: 'localities' | 'streets' | 'buildings';
  parent: string | null;
  label: string;
  value: string;
  onEdit: (value: string) => void;
  onSelect: (node: DirectoryNode) => void;
}) {
  const id = useId();
  const [options, setOptions] = useState<DirectoryNode[]>([]);
  const [focused, setFocused] = useState(false);
  const [active, setActive] = useState(-1);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  useEffect(() => {
    setOptions([]);
    setActive(-1);
    setError('');
    if (!focused || (!parent && value.trim().length < 2)) {
      setLoading(false);
      return;
    }
    const controller = new AbortController();
    setLoading(true);
    const timer = setTimeout(() => {
      api.addressDirectory
        .options(level, parent, value, controller.signal)
        .then((response) => {
          if (!controller.signal.aborted) {
            setOptions(response.data);
            setLoading(false);
          }
        })
        .catch(() => {
          if (!controller.signal.aborted) {
            setOptions([]);
            setLoading(false);
            setError('Справочник недоступен. Измените запрос для повтора.');
          }
        });
    }, 350);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [level, parent, value, focused]);
  const expanded = focused && options.length > 0;
  function select(node: DirectoryNode) {
    onSelect(node);
    setFocused(false);
    setOptions([]);
  }
  return (
    <div className="relative min-w-0 space-y-2">
      <label htmlFor={id} className="block text-14 text-ink-secondary">
        {label}
      </label>
      <Input
        id={id}
        role="combobox"
        value={value}
        inputSize="field"
        shape="field"
        tone="outline"
        autoComplete="off"
        aria-autocomplete="list"
        aria-expanded={expanded}
        aria-controls={`${id}-options`}
        aria-activedescendant={expanded && active >= 0 ? `${id}-option-${active}` : undefined}
        rightSlot={
          level === 'localities' ? (
            <ChevronDown aria-hidden="true" className="mr-3 size-5 text-ink-secondary" />
          ) : undefined
        }
        onFocus={() => setFocused(true)}
        onBlur={() => setFocused(false)}
        onChange={(event) => {
          onEdit(event.target.value);
          setFocused(true);
        }}
        onKeyDown={(event) => {
          if (event.key === 'Escape' && focused) {
            event.preventDefault();
            event.stopPropagation();
            setFocused(false);
          }
          if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setFocused(true);
            setActive((index) => {
              if (!options.length) return -1;
              if (index < 0) return event.key === 'ArrowDown' ? 0 : options.length - 1;
              return (
                (index + (event.key === 'ArrowDown' ? 1 : options.length - 1)) % options.length
              );
            });
          }
          if (event.key === 'Enter' && expanded && active >= 0) {
            event.preventDefault();
            select(options[active]);
          }
        }}
      />
      {expanded && (
        <div
          id={`${id}-options`}
          role="listbox"
          aria-label={label}
          className="absolute inset-x-0 top-full z-10 max-h-48 overflow-y-auto rounded-field border border-surface-border bg-surface shadow-card"
        >
          {options.map((node, index) => (
            <button
              id={`${id}-option-${index}`}
              type="button"
              role="option"
              aria-selected={index === active}
              key={node.externalId}
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => select(node)}
              className={`block w-full px-5 py-3 text-left text-14 hover:bg-surface-grey ${index === active ? 'bg-surface-grey' : ''}`}
            >
              {node.label || node.name}
            </button>
          ))}
        </div>
      )}
      {focused && loading && (
        <p role="status" className="text-12 text-ink-secondary">
          Поиск адреса…
        </p>
      )}
      {focused && !loading && !error && !options.length && (parent || value.length >= 2) && (
        <p className="text-12 text-ink-secondary">Ничего не найдено. Попробуйте уточнить адрес.</p>
      )}
      {error && (
        <p role="alert" className="text-14 text-brand-red">
          {error}
        </p>
      )}
    </div>
  );
}

export default function CheckoutAddress({
  onChange,
  initialCity,
  onSelection,
}: {
  onChange: (id: string | null) => void;
  initialCity?: DirectoryNode | null;
  onSelection?: (selection: DirectorySelection | null) => void;
}) {
  const [city, setCity] = useState<DirectoryNode | null>(initialCity ?? null);
  const [street, setStreet] = useState<DirectoryNode | null>(null);
  const [house, setHouse] = useState<DirectoryNode | null>(null);
  const [cityText, setCityText] = useState(initialCity?.name ?? '');
  const [streetText, setStreetText] = useState('');
  const [houseText, setHouseText] = useState('');
  const edited = useRef(false);
  useEffect(() => {
    if (initialCity && !edited.current) {
      setCity(initialCity);
      setCityText(initialCity.name);
    }
  }, [initialCity]);
  function invalidate() {
    setHouse(null);
    onChange(null);
    onSelection?.(null);
  }
  function clearStreet() {
    setStreet(null);
    setStreetText('');
    setHouseText('');
    invalidate();
  }
  return (
    <div className="space-y-4">
      <SearchLevel
        level="localities"
        parent={null}
        label="Город"
        value={cityText}
        onEdit={(value) => {
          edited.current = true;
          setCityText(value);
          setCity(null);
          clearStreet();
        }}
        onSelect={(node) => {
          edited.current = true;
          setCity(node);
          setCityText(node.name);
          clearStreet();
        }}
      />
      {city && (
        <>
          <SearchLevel
            key={`${city.externalId}-street`}
            level="streets"
            parent={city.externalId}
            label="Улица"
            value={streetText}
            onEdit={(value) => {
              setStreetText(value);
              setStreet(null);
              setHouseText('');
              invalidate();
            }}
            onSelect={(node) => {
              setStreet(node);
              setStreetText(node.label || node.name);
              setHouseText('');
              invalidate();
            }}
          />
          {street && (
            <button
              type="button"
              className="text-14 text-ink-secondary underline"
              onClick={clearStreet}
            >
              Без улицы
            </button>
          )}
          <SearchLevel
            key={street?.externalId ?? city.externalId}
            level="buildings"
            parent={street?.externalId ?? city.externalId}
            label="Дом / корпус / строение"
            value={houseText}
            onEdit={(value) => {
              setHouseText(value);
              invalidate();
            }}
            onSelect={(node) => {
              setHouse(node);
              setHouseText(node.label || node.name);
              onChange(node.externalId);
              onSelection?.({ city, street, house: node });
            }}
          />
        </>
      )}
      {house && (
        <p role="status" className="sr-only">
          Выбран дом: {house.label || house.name}
        </p>
      )}
    </div>
  );
}
