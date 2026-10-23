import { describe, expect, it } from 'vitest';
import type { ContactInfo, Store } from '../../../lib/api';
import { buildProductContacts } from './productContacts';

const empty: ContactInfo = {
  company_name: null,
  address: null,
  phones: [],
  emails: [],
  social_networks: [],
  map_embed: null,
  working_hours: [],
};
const store = (city: string, address: string, hours: string | null = null) =>
  ({ id: city.length, city, address, hours }) as Store;

describe('контакты карточки товара', () => {
  it('пусто в админке — телефон и часы футера, заглушка магазина, соцсети футера', () => {
    expect(buildProductContacts(null, [], 'Липецк')).toEqual({
      phone: '8 (800) 222-85-86',
      phoneHours: 'c 09.00 до 21.00 (Без перерыва и выходных)',
      store: {
        address: 'г. Липецк, ул. Римского-Корсакова, д. 1-А',
        hours: '08.30-17.30 (ПН-ПТ)   09.00-17.00 (СБ-ВС)',
      },
      social: [
        { kind: 'vk', url: 'https://vk.com' },
        { kind: 'whatsapp', url: 'https://wa.me/78002228586' },
      ],
    });
  });

  it('из админки: первый телефон, часы «день часы», соцсети VK и WhatsApp, прочие — мимо', () => {
    const contact: ContactInfo = {
      ...empty,
      phones: [{ phone: '8 (800) 111-11-11' }, { phone: '+7 (4742) 00-00-00' }],
      working_hours: [
        { day: 'Пн–Пт', hours: '9:00–20:00' },
        { day: 'Сб–Вс', hours: '10:00–18:00' },
      ],
      social_networks: [
        { name: 'Telegram', url: 'https://t.me/sv' },
        { name: 'ВКонтакте', url: 'https://vk.com/svetofor' },
        { name: 'WhatsApp', url: 'https://wa.me/79990000000' },
      ],
    };
    const c = buildProductContacts(contact, [], null);
    expect(c.phone).toBe('8 (800) 111-11-11');
    expect(c.phoneHours).toBe('Пн–Пт 9:00–20:00   Сб–Вс 10:00–18:00');
    expect(c.social).toEqual([
      { kind: 'vk', url: 'https://vk.com/svetofor' },
      { kind: 'whatsapp', url: 'https://wa.me/79990000000' },
    ]);
  });

  it('магазин — первый в городе покупателя; нет такого — адрес из админки', () => {
    const stores = [
      store('Москва', 'ул. Ленина, 1'),
      store('Липецк', 'пр. Победы, 5', '10–21'),
      store('Липецк', 'ул. Гагарина, 2'),
    ];
    expect(buildProductContacts(null, stores, 'липецк').store).toEqual({
      address: 'г. Липецк, пр. Победы, 5',
      hours: '10–21',
    });
    expect(
      buildProductContacts({ ...empty, address: 'г. Елец, ул. Мира, 3' }, stores, 'Елец').store,
    ).toEqual({
      address: 'г. Елец, ул. Мира, 3',
      hours: null,
    });
  });
});
