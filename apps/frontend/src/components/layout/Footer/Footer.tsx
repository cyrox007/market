import { Link } from 'react-router-dom';
import { Clock, Mail, Phone, Users } from 'lucide-react';
import { VkIcon, WhatsAppIcon } from '../../ui/icons/brands';
import { cn } from '../../../lib/cn';
import { PAGE_CONTAINER } from '../../../lib/layout';
import { organizationRequisites } from '../../../data/organizationRequisites';
import { FOOTER_COLUMNS, FOOTER_CONTACTS } from './lib/links';

const SOCIAL = 'flex items-center rounded-lg bg-brand-green p-2.5 text-ink-inverse';

/** Подвал по макету. Замеры — market-docs/25-footer.md */
export default function Footer() {
  return (
    <footer className="bg-surface-footer">
      <div className={cn(PAGE_CONTAINER, 'flex flex-col gap-6 py-10')}>
        <div className="flex items-start justify-between gap-10 max-md:flex-col">
          <Contacts />

          <div className="flex gap-[54px] max-sm:flex-wrap max-sm:gap-x-10 max-sm:gap-y-8">
            {FOOTER_COLUMNS.map((column) => (
              <nav key={column.title} className="flex flex-col gap-2.5" aria-label={column.title}>
                <h2 className="font-display text-18 font-semibold uppercase leading-none text-ink-inverse">
                  {column.title}
                </h2>
                {column.links.map((link) =>
                  link.to ? (
                    <Link
                      key={link.label}
                      to={link.to}
                      className="text-16 font-medium leading-none text-ink-inverse/[0.72] outline-none transition-colors hover:text-ink-inverse focus-visible:text-ink-inverse motion-reduce:transition-none"
                    >
                      {link.label}
                    </Link>
                  ) : (
                    <span
                      key={link.label}
                      aria-disabled="true"
                      className="cursor-default text-16 font-medium leading-none text-ink-inverse/40"
                    >
                      {link.label}
                    </span>
                  ),
                )}
              </nav>
            ))}
          </div>
        </div>

        <div className="h-px w-full bg-ink-inverse/[0.24]" />

        <div className="flex items-end justify-between gap-6 max-md:flex-col max-md:items-start">
          <div className="flex flex-col gap-3 text-14 font-semibold leading-none text-ink-inverse/[0.46]">
            {requisiteLines().map((line) => (
              <p key={line}>{line}</p>
            ))}
            <p className="uppercase">
              © {new Date().getFullYear()} {organizationRequisites.fullName}. Все права защищены.
            </p>
          </div>
          <p className="text-14 font-semibold uppercase leading-none text-ink-inverse/[0.46]">
            Разработано компанией «Название компании»
          </p>
        </div>
      </div>
    </footer>
  );
}

/** Источник один — тот же файл, что у оферты и эквайринга */
function requisiteLines() {
  const r = organizationRequisites;
  return [
    `ИНН ${r.inn}, КПП ${r.kpp}, ОГРН ${r.ogrn}, ОКПО ${r.okpo}`,
    `Юридический адрес: ${r.legalAddress}`,
    `Генеральный директор: ${r.director}`,
  ];
}

function Contacts() {
  return (
    <div className="flex flex-col justify-center gap-4">
      <a
        href={`tel:${FOOTER_CONTACTS.phone.replace(/\D/g, '')}`}
        className="flex items-end gap-3 text-18 font-medium leading-none text-ink-inverse outline-none transition-opacity hover:opacity-80 focus-visible:opacity-80 motion-reduce:transition-none"
      >
        <Phone className="size-6 shrink-0" />
        {FOOTER_CONTACTS.phone}
      </a>

      <a
        href={`mailto:${FOOTER_CONTACTS.email}`}
        className="flex items-center gap-3 text-18 font-medium leading-none text-ink-inverse outline-none transition-opacity hover:opacity-80 focus-visible:opacity-80 motion-reduce:transition-none"
      >
        <Mail className="size-6 shrink-0" />
        {FOOTER_CONTACTS.email}
      </a>

      <p className="flex items-start gap-3 text-18 font-medium text-ink-inverse">
        <Clock className="size-6 shrink-0" />
        <span className="flex flex-col gap-1 leading-none">
          {FOOTER_CONTACTS.hours.map((line) => (
            <span key={line}>{line}</span>
          ))}
        </span>
      </p>

      <div className="flex items-center gap-3">
        <Users className="size-6 shrink-0 text-ink-inverse" aria-hidden="true" />
        <div className="flex items-center gap-2">
          <a
            href="https://vk.com"
            target="_blank"
            rel="noreferrer"
            aria-label="ВКонтакте"
            className={SOCIAL}
          >
            <VkIcon className="size-6" />
          </a>
          <a
            href="https://wa.me/78002228586"
            target="_blank"
            rel="noreferrer"
            aria-label="WhatsApp"
            className={SOCIAL}
          >
            <WhatsAppIcon className="size-6" />
          </a>
        </div>
      </div>
    </div>
  );
}
