import type { ReactNode } from 'react';
import mapPinIcon from '../../../assets/icons/contact-map-pin.svg';
import phoneIcon from '../../../assets/icons/contact-phone.svg';
import { VkIcon, WhatsAppIcon } from '../../../components/ui/icons/brands';
import type { ProductContacts as Contacts, SocialKind } from '../lib/productContacts';

const SOCIAL: Record<SocialKind, { label: string; Icon: typeof VkIcon }> = {
  vk: { label: 'ВКонтакте', Icon: VkIcon },
  whatsapp: { label: 'WhatsApp', Icon: WhatsAppIcon },
};

/** Серый блок под вкладками (Figma 919:75784; < 980 — друг под другом, 919:76331, 919:76839) */
export default function ProductContacts({ contacts }: { contacts: Contacts }) {
  const { phone, phoneHours, store, social } = contacts;
  return (
    <section
      aria-label="Контакты"
      className="mb-6 flex items-start justify-between gap-6 rounded-[8px] bg-surface-grey px-6 py-5 max-md:flex-col max-vsm:p-4"
    >
      <Column title="Остались вопросы? Звоните нам:">
        <Item icon={phoneIcon} sub={phoneHours}>
          <a
            href={`tel:${phone.replace(/[^\d+]/g, '')}`}
            className="transition-colors hover:text-brand-green"
          >
            {phone}
          </a>
        </Item>
      </Column>
      <Column title="Посещайте наши магазины">
        <Item icon={mapPinIcon} sub={store.hours}>
          {store.address}
        </Item>
      </Column>
      <Column title="Присоединяйтесь к нам в соц. сетях">
        <div className="flex gap-2">
          {social.map(({ kind, url }) => {
            const { label, Icon } = SOCIAL[kind];
            return (
              <a
                key={kind}
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                className="flex size-11 items-center justify-center rounded-[8px] bg-brand-green text-ink-inverse transition-transform duration-fast hover:-translate-y-0.5 motion-reduce:transition-none max-vsm:size-9"
              >
                <Icon className="size-6" />
              </a>
            );
          })}
        </div>
      </Column>
    </section>
  );
}

function Column({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-2">
      <h3 className="font-display text-16 font-semibold uppercase text-ink-secondary max-vsm:text-14">
        {title}
      </h3>
      {children}
    </div>
  );
}

function Item({ icon, sub, children }: { icon: string; sub: string | null; children: ReactNode }) {
  return (
    <div className="flex items-center gap-3 max-vsm:gap-2">
      <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-yellow max-vsm:size-9">
        <img src={icon} alt="" width={20} height={20} />
      </span>
      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="text-18 font-medium text-ink max-vsm:text-14">{children}</span>
        {sub && <span className="whitespace-pre-wrap text-12 text-ink-secondary">{sub}</span>}
      </div>
    </div>
  );
}
