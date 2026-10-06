import { Link } from 'react-router-dom';
import type { LinkProps } from 'react-router-dom';

type SmartLinkProps = Omit<LinkProps, 'to'> & { to: string };

/** Адрес со схемой (https:, tel:, mailto:) или протокол-относительный //host */
const HAS_SCHEME = /^([a-z][a-z\d+.-]*:|\/\/)/i;
const WEB = /^(https?:)?\/\//i;

/**
 * Ссылка на адрес из админки: внутренний — через роутер, внешний сайт — обычной ссылкой
 * в новой вкладке, tel: / mailto: — обычной ссылкой. market-docs/40
 */
export default function SmartLink({ to, ...rest }: SmartLinkProps) {
  if (!HAS_SCHEME.test(to)) return <Link to={to} {...rest} />;
  if (WEB.test(to)) return <a href={to} target="_blank" rel="noopener noreferrer" {...rest} />;
  return <a href={to} {...rest} />;
}
