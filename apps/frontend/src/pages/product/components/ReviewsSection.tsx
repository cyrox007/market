import { useState } from 'react';
import Button from '../../../components/ui/primitives/Button';
import type { Review } from '../../../lib/api';
import { plural } from '../../../lib/plural';
import type { ReviewsStatus } from '../hooks/useProductReviews';
import { formatRating } from '../lib/productText';
import { formatReviewDate } from '../lib/reviewDate';
import RatingStars from './RatingStars';

const PAGE = 5;
const REVIEW_FORMS = ['отзыв', 'отзыва', 'отзывов'] as const;

interface Props {
  rating: number;
  count: number;
  reviews: Review[];
  status: ReviewsStatus;
  onWriteReview: () => void;
  onRetry: () => void;
}

/** Вкладка «Отзывы»: сводка справа на 1440, над списком ниже 980 (Figma 919:75766, 919:76240, 919:76748) */
export default function ReviewsSection({
  rating,
  count,
  reviews,
  status,
  onWriteReview,
  onRetry,
}: Props) {
  const [shown, setShown] = useState(PAGE);
  const rest = Math.min(PAGE, reviews.length - shown);

  return (
    <div className="flex flex-row-reverse items-start gap-6 pt-3 max-md:flex-col">
      <section
        aria-label="Оценка товара"
        className="flex w-[442px] shrink-0 flex-col gap-5 rounded-[12px] bg-surface-grey p-6 max-md:w-full max-md:flex-row max-md:items-center max-md:justify-between max-vsm:flex-col max-vsm:items-stretch max-vsm:gap-4 max-vsm:p-4"
      >
        <div className="flex items-center gap-3">
          <span className="text-24 font-bold text-ink">{formatRating(rating)}</span>
          <div className="flex flex-col gap-1">
            <RatingStars value={rating} />
            <span className="text-14 text-ink-secondary">
              {count} {plural(count, REVIEW_FORMS)}
            </span>
          </div>
        </div>
        <div className="flex w-full flex-col gap-3 max-md:w-[280px] max-vsm:w-full">
          <Button
            variant="muted"
            size="lg"
            shape="rounded"
            fullWidth
            className="max-vsm:h-[41px] max-vsm:text-14"
            onClick={onWriteReview}
          >
            Написать отзыв
          </Button>
          <p className="text-center text-12 text-ink-secondary max-vsm:text-left">
            Отзыв появится на сайте после проверки
          </p>
        </div>
      </section>

      <div className="flex w-full min-w-0 flex-1 flex-col gap-3">
        {status === 'loading' && (
          <div aria-label="Отзывы загружаются" aria-busy="true" className="flex flex-col gap-3">
            {[0, 1, 2].map((i) => (
              <div key={i} className="h-[128px] animate-pulse rounded-[12px] bg-surface-grey" />
            ))}
          </div>
        )}
        {status === 'error' && (
          <div className="flex flex-col items-center gap-3 rounded-[12px] border border-surface-border p-6 text-center">
            <p className="text-14 text-ink-secondary">Не удалось загрузить отзывы</p>
            <Button variant="outline" size="sm" shape="rounded" onClick={onRetry}>
              Повторить
            </Button>
          </div>
        )}
        {reviews.slice(0, shown).map((review) => (
          <ReviewCard key={review.id} review={review} />
        ))}
        {rest > 0 && (
          <div className="flex justify-center pt-3">
            <Button
              variant="outline"
              size="lg"
              shape="rounded"
              className="px-7 max-vsm:h-[47px]"
              onClick={() => setShown((n) => n + PAGE)}
            >
              Показать ещё {rest} {plural(rest, REVIEW_FORMS)}
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}

function ReviewCard({ review }: { review: Review }) {
  const name = review.name.trim();
  return (
    <article className="flex flex-col gap-3 rounded-[12px] border border-surface-border bg-white p-5 motion-safe:animate-photoIn">
      <div className="flex items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          <span
            aria-hidden
            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface-grey text-16 font-semibold text-ink"
          >
            {name.charAt(0).toUpperCase() || '?'}
          </span>
          <div className="flex min-w-0 flex-col gap-1">
            <span className="truncate text-16 font-semibold text-ink">{name}</span>
            <span className="text-14 text-ink-secondary">
              {formatReviewDate(review.created_at)}
            </span>
          </div>
        </div>
        <RatingStars value={review.rating} />
      </div>
      <p className="whitespace-pre-line break-words text-14 text-ink">{review.comment}</p>
    </article>
  );
}
