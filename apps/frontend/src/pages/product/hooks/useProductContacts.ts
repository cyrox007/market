import useSWR from 'swr';
import { api } from '../../../lib/api';
import { buildProductContacts } from '../lib/productContacts';

const OPTIONS = { revalidateOnFocus: false, dedupingInterval: 300_000 };

/** Контакты из админки и магазин города; пока не пришли — запасные значения (футер, заглушка) */
export function useProductContacts(city: string | null) {
  const { data: contact } = useSWR('/contact', () => api.contact.get(), OPTIONS);
  const { data: stores } = useSWR(
    city ? ['/stores', city] : null,
    () => api.stores.list({ city: city! }),
    OPTIONS,
  );
  return buildProductContacts(contact?.contact ?? null, stores?.data ?? [], city);
}
