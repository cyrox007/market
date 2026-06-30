import { useState, useRef, useEffect, useCallback, useId } from 'react';
import { api, type ShippingLocation } from '../../lib/api';

const SEARCH_DEBOUNCE_MS = 300;
const INITIAL_LIST_LIMIT = 50;

interface LocationSearchInputProps {
  value: number | null;
  selectedDisplayName: string;
  onChange: (location: ShippingLocation) => void;
  initialLocations: ShippingLocation[];
  placeholder?: string;
  required?: boolean;
  className?: string;
  'aria-label'?: string;
}

export default function LocationSearchInput({
  value,
  selectedDisplayName,
  onChange,
  initialLocations,
  placeholder = 'Найти город или регион...',
  required = false,
  className = '',
  'aria-label': ariaLabel = 'Локация доставки',
}: LocationSearchInputProps) {
  const listId = useId();
  const safeInitialLocations = Array.isArray(initialLocations) ? initialLocations : [];
  const [query, setQuery] = useState('');
  const [searchResults, setSearchResults] = useState<ShippingLocation[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [highlightIndex, setHighlightIndex] = useState(-1);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const searchLocations = useCallback(async (searchQuery: string) => {
    const q = searchQuery.trim();
    if (!q) {
      setSearchResults(safeInitialLocations.slice(0, INITIAL_LIST_LIMIT));
      return;
    }
    setIsLoading(true);
    try {
      const response = await api.shipping.getLocations({ type: 'locality', search: q }) as { data?: ShippingLocation[] };
      setSearchResults(Array.isArray(response?.data) ? response.data : []);
    } catch (err) {
      console.error('Location search failed:', err);
      setSearchResults([]);
    } finally {
      setIsLoading(false);
    }
  }, [safeInitialLocations]);

  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (!isOpen) return;
    debounceRef.current = setTimeout(() => {
      searchLocations(query);
      debounceRef.current = null;
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [query, isOpen, searchLocations]);

  const displayList = query.trim() ? searchResults : safeInitialLocations.slice(0, INITIAL_LIST_LIMIT);
  const showDropdown = isOpen && (displayList.length > 0 || isLoading);

  const handleSelect = useCallback((loc: ShippingLocation) => {
    onChange(loc);
    setQuery('');
    setIsOpen(false);
    setHighlightIndex(-1);
  }, [onChange]);

  const handleFocus = useCallback(() => {
    setIsOpen(true);
    setHighlightIndex(-1);
    if (value != null) setQuery('');
    if (!query) setSearchResults(safeInitialLocations.slice(0, INITIAL_LIST_LIMIT));
  }, [query, safeInitialLocations, value]);

  const handleBlur = useCallback(() => {
    setTimeout(() => {
      if (!containerRef.current?.contains(document.activeElement)) {
        setIsOpen(false);
        setHighlightIndex(-1);
      }
    }, 150);
  }, []);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (!showDropdown) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlightIndex((i) => (i < displayList.length - 1 ? i + 1 : 0));
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightIndex((i) => (i > 0 ? i - 1 : displayList.length - 1));
      return;
    }
    if (e.key === 'Enter' && highlightIndex >= 0 && displayList[highlightIndex]) {
      e.preventDefault();
      handleSelect(displayList[highlightIndex]);
      return;
    }
    if (e.key === 'Escape') {
      setIsOpen(false);
      setHighlightIndex(-1);
    }
  }, [showDropdown, displayList, highlightIndex, handleSelect]);

  const isShowingSelection = value != null && selectedDisplayName && !isOpen;

  return (
    <div ref={containerRef} className="relative">
      <div className="relative">
        <input
          ref={inputRef}
          type="text"
          value={isShowingSelection ? selectedDisplayName : query}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
            setHighlightIndex(-1);
          }}
          onFocus={handleFocus}
          onBlur={handleBlur}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          required={required}
          aria-label={ariaLabel}
          aria-expanded={isOpen}
          aria-autocomplete="list"
          aria-controls={listId}
          id={listId}
          className={`w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none ${className}`}
        />
        <span className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
          {isLoading ? (
            <span className="animate-pulse">...</span>
          ) : (
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          )}
        </span>
      </div>
      {showDropdown && (
        <ul
          id={listId}
          role="listbox"
          className="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto"
        >
          {isLoading && displayList.length === 0 ? (
            <li className="px-4 py-3 text-gray-500 text-sm">Поиск...</li>
          ) : (
            displayList.map((loc, index) => (
              <li
                key={loc.id}
                role="option"
                aria-selected={value === loc.id}
                className={`px-4 py-3 text-sm cursor-pointer ${
                  value === loc.id ? 'bg-red-50 text-red-700 font-medium' : ''
                } ${index === highlightIndex ? 'bg-gray-100' : 'hover:bg-gray-50'}`}
                onMouseDown={(e) => {
                  e.preventDefault();
                  handleSelect(loc);
                }}
              >
                {loc?.name ?? ''}
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  );
}
