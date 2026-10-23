import { FOOTER_CONTACTS } from '../../../components/layout/Footer/lib/links';
import type { ContactInfo, Store } from '../../../lib/api';

export type SocialKind = 'vk' | 'whatsapp';

export interface ProductContacts {
  phone: string;
  phoneHours: string;
  store: { address: string; hours: string | null };
  social: { kind: SocialKind; url: string }[];
}

/** Заглушка макета (Figma 919:75794), пока в админке нет адреса и в городе нет магазина */
const STORE_STUB = {
  address: 'г. Липецк, ул. Римского-Корсакова, д. 1-А',
  hours: '08.30-17.30 (ПН-ПТ)   09.00-17.00 (СБ-ВС)',
};

const SOCIAL: { kind: SocialKind; test: RegExp }[] = [
  { kind: 'vk', test: /vk\.com|вконтакте|^vk$/i },
  { kind: 'whatsapp', test: /wa\.me|whatsapp/i },
];

const text = (value: string | null | undefined) => value?.trim() || '';

/** Блок «Остались вопросы?»: админка (`/contact`) → магазин города (`/stores`) → футер и заглушка */
export function buildProductContacts(
  contact: ContactInfo | null,
  stores: Store[],
  city: string | null,
): ProductContacts {
  const phone = contact?.phones.map((p) => text(p.phone)).find(Boolean) || FOOTER_CONTACTS.phone;
  const hours = (contact?.working_hours ?? [])
    .map((h) => [text(h.day), text(h.hours)].filter(Boolean).join(' '))
    .filter(Boolean);

  const wanted = city?.trim().toLowerCase();
  const local = wanted ? stores.find((s) => s.city?.trim().toLowerCase() === wanted) : undefined;
  const store = local
    ? { address: `г. ${local.city}, ${local.address}`, hours: text(local.hours) || null }
    : text(contact?.address)
      ? { address: text(contact?.address), hours: null }
      : STORE_STUB;

  const fromAdmin = SOCIAL.flatMap(({ kind, test }) => {
    const hit = contact?.social_networks.find(
      (n) => test.test(`${text(n.name)} ${text(n.url)}`) && text(n.url),
    );
    return hit ? [{ kind, url: text(hit.url) }] : [];
  });

  return {
    phone,
    phoneHours: hours.length ? hours.join('   ') : FOOTER_CONTACTS.hours.join(' '),
    store,
    social: fromAdmin.length
      ? fromAdmin
      : [
          { kind: 'vk', url: FOOTER_CONTACTS.vk },
          { kind: 'whatsapp', url: FOOTER_CONTACTS.whatsapp },
        ],
  };
}
