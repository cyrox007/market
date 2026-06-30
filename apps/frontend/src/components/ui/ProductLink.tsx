import { ReactNode, MouseEvent } from 'react';
import { Link } from 'react-router-dom';
import { usePrefetchProduct } from '../../hooks/usePrefetchProduct';

interface ProductLinkProps {
  to: string;
  children: ReactNode;
  className?: string;
  onClick?: (e: MouseEvent<HTMLAnchorElement>) => void;
  onMouseEnter?: (e: MouseEvent<HTMLAnchorElement>) => void;
  [key: string]: unknown;
}

const PRODUCT_PATH = /^\/product\/([^/?#]+)/;

export default function ProductLink({ to, children, className, onClick, onMouseEnter, ...props }: ProductLinkProps) {
  const prefetchProduct = usePrefetchProduct();

  const handleClick = (e: MouseEvent<HTMLAnchorElement>) => {
    if (onClick) {
      onClick(e);
    }
  };

  const prefetchFromTo = () => {
    const match = typeof to === 'string' ? to.match(PRODUCT_PATH) : null;
    if (match?.[1]) {
      prefetchProduct(decodeURIComponent(match[1]));
    }
  };

  const handleMouseEnter = (e: MouseEvent<HTMLAnchorElement>) => {
    prefetchFromTo();
    onMouseEnter?.(e);
  };

  return (
    <Link
      to={to}
      onClick={handleClick}
      onMouseEnter={handleMouseEnter}
      onPointerDown={prefetchFromTo}
      className={className}
      {...props}
    >
      {children}
    </Link>
  );
}
