import starEmpty from '../../../assets/icons/review-star-empty.svg';
import starFilled from '../../../assets/icons/review-star-filled.svg';
import { formatRating } from '../lib/productText';

/** Пять звёзд 16 px (Figma 917:66373); дробная оценка округляется до целых звёзд */
export default function RatingStars({ value }: { value: number }) {
  const filled = Math.round(value);
  const label = Number.isInteger(value) ? String(value) : formatRating(value);
  return (
    <span role="img" aria-label={`Оценка ${label} из 5`} className="flex shrink-0 gap-1">
      {[1, 2, 3, 4, 5].map((n) => (
        <img key={n} src={n <= filled ? starFilled : starEmpty} alt="" width={16} height={16} />
      ))}
    </span>
  );
}
