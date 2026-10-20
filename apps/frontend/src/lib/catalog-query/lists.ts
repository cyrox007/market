import { LIST_KEYS, type ListKey } from './types';

const ATTR_PREFIX = 'attr_';

/** По алфавиту: порядок отметок не меняет адрес */
const sorted = (values: string[]) => [...values].sort();

/** Принимает и «a,b», и повтор ключа; без пустых и повторов */
function readValues(params: URLSearchParams, name: string): string[] {
  const values = params.getAll(name).flatMap((v) => v.split(','));
  return [...new Set(values.map((v) => v.trim()).filter(Boolean))];
}

export function readLists(params: URLSearchParams): Record<ListKey, string[]> {
  return Object.fromEntries(
    Object.entries(LIST_KEYS).map(([field, name]) => [field, readValues(params, name)]),
  ) as Record<ListKey, string[]>;
}

export function readAttributes(params: URLSearchParams): Record<string, string[]> {
  const attributes: Record<string, string[]> = {};
  for (const name of new Set(params.keys())) {
    if (!name.startsWith(ATTR_PREFIX)) continue;
    const values = readValues(params, name);
    if (values.length) attributes[name.slice(ATTR_PREFIX.length)] = values;
  }
  return attributes;
}

export function writeLists(params: URLSearchParams, lists: Record<ListKey, string[]>): void {
  for (const [field, name] of Object.entries(LIST_KEYS)) {
    const values = lists[field as ListKey];
    if (values.length) params.set(name, sorted(values).join(','));
  }
}

export function writeAttributes(params: URLSearchParams, attributes: Record<string, string[]>) {
  for (const slug of sorted(Object.keys(attributes))) {
    const values = attributes[slug];
    if (values.length) params.set(`${ATTR_PREFIX}${slug}`, sorted(values).join(','));
  }
}
