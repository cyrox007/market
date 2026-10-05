import { Link } from 'react-router-dom';
import { Clock, Mail, Phone, Users } from 'lucide-react';
import { VkIcon, WhatsAppIcon } from '../../ui/icons/brands';
import { organizationRequisites } from '../../../data/organizationRequisites';
import { FOOTER_COLUMNS, FOOTER_CONTACTS } from './lib/links';

/**
 * Свой контейнер, а не PAGE_CONTAINER: ниже 550 подвал держит 24, остальной сайт — 16.
 * Замеры трёх состояний — market-docs/25-footer.md
 */
const CONTAINER = 'mx-auto flex w-full max-w-[1440px] flex-col gap-6 px-[100px] py-10 max-md:px-6';

const SOCIAL = 'flex items-center rounded-lg bg-brand-green p-2.5 text-ink-inverse';

/** Иконки у контактов есть только на десктопе */
const CONTACT_ICON = 'size-6 shrink-0 max-md:hidden';

const LINK_TEXT = 'text-16 font-medium leading-none max-vsm:text-14 max-vsm:font-normal';

export default function Footer() {
  return (
    <footer className="bg-surface-footer">
      <div className={CONTAINER}>
        <div className="flex items-start justify-between gap-10 max-md:flex-col max-md:gap-6">
          <Contacts />

          <div className="flex gap-[54px] max-md:grid max-md:w-full max-md:grid-cols-2 max-md:gap-4 max-vsm:grid-cols-1 max-vsm:gap-[23px]">
            {FOOTER_COLUMNS.map((column) => (
              <nav key={column.title} className="flex flex-col gap-2.5" aria-label={column.title}>
                <h2 className="font-display text-18 font-semibold uppercase leading-none text-ink-inverse max-vsm:text-16">
                  {column.title}
                </h2>
                {column.links.map((link) =>
                  link.to ? (
                    <Link
                      key={link.label}
                      to={link.to}
                      className={`${LINK_TEXT} text-ink-inverse/[0.72] outline-none transition-colors hover:text-ink-inverse focus-visible:text-ink-inverse motion-reduce:transition-none`}
                    >
                      {link.label}
                    </Link>
                  ) : (
                    <span
                      key={link.label}
                      aria-disabled="true"
                      className={`${LINK_TEXT} cursor-default text-ink-inverse/40`}
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

        <div className="flex items-end justify-between gap-6 text-14 font-semibold leading-none text-ink-inverse/[0.46] max-md:flex-col max-md:items-start max-md:gap-3">
          <div className="flex flex-col gap-3">
            {requisiteLines().map((line) => (
              <p key={line}>{line}</p>
            ))}
            <p className="uppercase">
              © {new Date().getFullYear()} {organizationRequisites.fullName}. Все права защищены.
            </p>
          </div>
          <p className="uppercase">Разработано компанией «Название компании»</p>
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

/**
 * Десктоп — столбец с иконками. Ниже 980 — сетка 2 × 2 без иконок: телефон и почта
 * в первом ряду, часы и соцсети во втором. Ниже 550 — снова столбец.
 */
function Contacts() {
  return (
    <div className="grid grid-cols-1 gap-4 max-md:w-full max-md:grid-cols-2 max-vsm:grid-cols-1">
      <a
        href={`tel:${FOOTER_CONTACTS.phone.replace(/\D/g, '')}`}
        className="flex items-end gap-3 text-18 font-medium leading-none text-ink-inverse outline-none transition-opacity hover:opacity-80 focus-visible:opacity-80 motion-reduce:transition-none"
      >
        <Phone className={CONTACT_ICON} />
        {FOOTER_CONTACTS.phone}
      </a>

      <a
        href={`mailto:${FOOTER_CONTACTS.email}`}
        className="flex items-center gap-3 text-18 font-medium leading-none text-ink-inverse outline-none transition-opacity hover:opacity-80 focus-visible:opacity-80 motion-reduce:transition-none"
      >
        <Mail className={CONTACT_ICON} />
        {FOOTER_CONTACTS.email}
      </a>

      <p className="flex items-start gap-3 text-18 font-medium text-ink-inverse">
        <Clock className={CONTACT_ICON} />
        <span className="flex flex-col gap-1 leading-none">
          {FOOTER_CONTACTS.hours.map((line) => (
            <span key={line}>{line}</span>
          ))}
        </span>
      </p>

      <div className="flex items-center gap-3">
        <Users className={`${CONTACT_ICON} text-ink-inverse`} aria-hidden="true" />
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
