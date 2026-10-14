const BAR = 'max-w-full rounded-badge';

/** Заглушка карточки товара — Figma 544:19440: фото, название в две строки, цена */
export default function ProductCardSkeleton() {
  return (
    <div className="flex animate-pulse flex-col gap-3 motion-reduce:animate-none" aria-hidden>
      <div className="aspect-square rounded-btn bg-surface-grey" />
      <div className={`${BAR} h-5 w-full bg-surface-grey`} />
      <div className={`${BAR} h-5 w-[150px] bg-surface-grey`} />
      <div className={`${BAR} h-6 w-24 bg-surface-border`} />
    </div>
  );
}
