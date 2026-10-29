import { FileText, ListChecks, PenLine } from 'lucide-react';
import Button from '../../../components/ui/primitives/Button';

const ICONS = { description: FileText, specs: ListChecks, reviews: PenLine };

interface Props {
  icon: keyof typeof ICONS;
  title: string;
  text: string;
  action?: { label: string; onClick: () => void };
}

/** Пустая вкладка карточки (Figma `reviews-empty-1440`, market-docs/57-задачи/08) */
export default function ProductEmptyState({ icon, title, text, action }: Props) {
  const Icon = ICONS[icon];
  return (
    <div className="flex flex-1 flex-col items-center justify-center rounded-[16px] bg-surface-grey px-4 py-10 text-center">
      <span className="mb-5 flex size-[60px] items-center justify-center rounded-full bg-white text-ink-secondary max-vsm:mb-4 max-vsm:size-12">
        <Icon aria-hidden className="size-6" strokeWidth={1.5} />
      </span>
      <h3 className="text-24 font-semibold text-ink max-vsm:text-20">{title}</h3>
      <p className="mt-2 max-w-[360px] whitespace-pre-line text-14 text-ink-secondary">{text}</p>
      {action && (
        <Button
          size="lg"
          shape="rounded"
          className="mt-4 min-w-[200px] shadow-btn max-vsm:h-11"
          onClick={action.onClick}
        >
          {action.label}
        </Button>
      )}
    </div>
  );
}

export function ReviewsEmpty({ onWriteReview }: { onWriteReview: () => void }) {
  return (
    <ProductEmptyState
      icon="reviews"
      title="Отзывов пока нет"
      text={'Купили этот товар? Расскажите, как он вам,\nи помогите другим с выбором.'}
      action={{ label: 'Написать отзыв', onClick: onWriteReview }}
    />
  );
}
