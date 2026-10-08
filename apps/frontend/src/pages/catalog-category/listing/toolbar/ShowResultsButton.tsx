import { Button } from '../../../../components/ui/primitives';
import { formatNumber } from '../../../../components/ui/primitives/digits';
import { plural } from '../../../../lib/plural';

interface ShowResultsButtonProps {
  count: { value: number | null; loading: boolean };
  onClick: () => void;
}

const PRODUCTS = ['товар', 'товара', 'товаров'] as const;

/** «Показать N товаров»: пока считаем или не посчитали — без числа, применить можно (market-docs/44) */
export default function ShowResultsButton({ count, onClick }: ShowResultsButtonProps) {
  const nothing = count.value === 0;
  const label = nothing
    ? 'Ничего не найдено'
    : count.value === null
      ? 'Показать товары'
      : `Показать ${formatNumber(count.value)} ${plural(count.value, PRODUCTS)}`;

  return (
    <Button
      size="lg"
      shape="rounded"
      fullWidth
      disabled={nothing}
      aria-busy={count.loading || undefined}
      leftIcon={
        count.loading ? (
          <span
            aria-hidden="true"
            className="size-[1em] animate-spin rounded-full border-2 border-current border-t-transparent"
          />
        ) : undefined
      }
      onClick={onClick}
      className="shadow-card"
    >
      {label}
    </Button>
  );
}
