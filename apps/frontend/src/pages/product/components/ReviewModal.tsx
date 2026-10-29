import { useId, useState } from 'react';
import { Check } from 'lucide-react';
import starEmpty from '../../../assets/icons/review-star-empty.svg';
import starFilled from '../../../assets/icons/review-star-filled.svg';
import Button from '../../../components/ui/primitives/Button';
import Modal from '../../../components/ui/primitives/Modal';
import { api } from '../../../lib/api';
import { apiErrorMessage } from '../../../lib/api-error';
import { cn } from '../../../lib/cn';

const MIN = 25;
const MAX = 1000;

interface Props {
  open: boolean;
  onClose: () => void;
  productId: number;
  /** Имя из профиля: полей имени и почты в окне нет */
  authorName: string;
}

/** «Отзыв о товаре» → «Отзыв отправлен» (скриншоты владельца 20.10, market-docs/57-задачи/11) */
export default function ReviewModal({ open, onClose, productId, authorName }: Props) {
  const [rating, setRating] = useState(0);
  const [hover, setHover] = useState(0);
  const [text, setText] = useState('');
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const hintId = useId();

  const comment = text.trim();
  const canSend = rating > 0 && comment.length >= MIN && !sending;

  const close = () => {
    onClose();
    // Отправленный отзыв больше не нужен; черновик остаётся до ухода со страницы
    if (sent) {
      setSent(false);
      setRating(0);
      setText('');
    }
  };

  const submit = async () => {
    if (!canSend) return;
    setSending(true);
    setError(null);
    try {
      await api.reviews.create(productId, { name: authorName, rating, comment });
      setSent(true);
    } catch (err) {
      setError(apiErrorMessage(err, 'Не удалось отправить отзыв. Попробуйте ещё раз.'));
    } finally {
      setSending(false);
    }
  };

  if (sent) {
    return (
      <Modal open={open} onClose={close} title="Отзыв отправлен">
        <div className="flex flex-col items-center text-center">
          <span className="flex size-[60px] items-center justify-center rounded-full bg-surface-grey text-ink-secondary motion-safe:animate-photoIn">
            <Check aria-hidden className="size-7" strokeWidth={1.5} />
          </span>
          <p className="mt-4 text-16 font-semibold text-ink">Спасибо, что поделились мнением</p>
          <p className="mt-2 text-14 text-ink-secondary">
            Отзыв появится на странице товара после проверки
          </p>
          <Button size="lg" shape="rounded" fullWidth className="mt-6" onClick={close}>
            Вернуться к товару
          </Button>
        </div>
      </Modal>
    );
  }

  const shown = hover || rating;
  return (
    <Modal open={open} onClose={close} title="Отзыв о товаре">
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          void submit();
        }}
      >
        <fieldset>
          <legend className="text-14 text-ink">Ваша оценка</legend>
          <div
            role="radiogroup"
            aria-label="Ваша оценка"
            className="mt-2 flex gap-2"
            onMouseLeave={() => setHover(0)}
          >
            {[1, 2, 3, 4, 5].map((n) => (
              <label
                key={n}
                onMouseEnter={() => setHover(n)}
                className="cursor-pointer rounded-[4px] transition-transform duration-fast hover:scale-110 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-green motion-reduce:transition-none"
              >
                <input
                  type="radio"
                  name="rating"
                  value={n}
                  checked={rating === n}
                  onChange={() => setRating(n)}
                  aria-label={`${n} из 5`}
                  className="sr-only"
                />
                <img src={n <= shown ? starFilled : starEmpty} alt="" width={32} height={32} />
              </label>
            ))}
          </div>
        </fieldset>

        <textarea
          value={text}
          onChange={(event) => setText(event.target.value)}
          maxLength={MAX}
          aria-label="Текст отзыва"
          aria-describedby={hintId}
          placeholder="Расскажите о покупке: качество, сборка, доставка"
          className="mt-4 block h-[160px] w-full resize-none rounded-btn border border-surface-border bg-surface p-4 text-14 text-ink outline-none transition-colors placeholder:text-ink-secondary focus:border-ink-secondary motion-reduce:transition-none"
        />
        <div id={hintId} className="mt-2 flex justify-between gap-4 text-12 text-ink-secondary">
          <span>
            От {MIN} до {MAX} символов
          </span>
          <span className={cn(comment.length > 0 && comment.length < MIN && 'text-brand-red')}>
            {text.length} из {MAX}
          </span>
        </div>

        {error && (
          <p role="alert" className="mt-3 text-14 text-brand-red">
            {error}
          </p>
        )}

        <Button
          type="submit"
          size="lg"
          shape="rounded"
          fullWidth
          className="mt-4"
          disabled={!canSend}
          isLoading={sending}
        >
          Отправить отзыв
        </Button>
        <p className="mt-3 text-center text-12 text-ink-secondary">
          Отзыв появится на сайте после проверки
        </p>
      </form>
    </Modal>
  );
}
