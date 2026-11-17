import { Link, type LinkProps } from 'react-router-dom';
import { useCartPanel } from '../../contexts/cart-panel-context';

/** href сохраняется для новой вкладки; обычный клик открывает общую панель. */
export default function CartLink({ onClick, ...props }: Omit<LinkProps, 'to'>) {
  const { openCart } = useCartPanel();
  return (
    <Link
      {...props}
      to="/cart"
      onClick={(event) => {
        onClick?.(event);
        if (
          event.defaultPrevented ||
          event.button !== 0 ||
          event.metaKey ||
          event.ctrlKey ||
          event.shiftKey ||
          event.altKey ||
          props.target === '_blank'
        )
          return;
        event.preventDefault();
        openCart();
      }}
    />
  );
}
