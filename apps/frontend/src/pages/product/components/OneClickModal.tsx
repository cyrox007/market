import { useId, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import PhoneInput from '../../../components/ui/PhoneInput';
import { Button, Checkbox, Input, Modal } from '../../../components/ui/primitives';
import { cn } from '../../../lib/cn';

interface Props {
  open: boolean;
  onClose: () => void;
  /** SKU к оформлению: выбранный вариант, иначе сам товар; null — вариант ещё не выбран */
  productId: number | null;
  quantity: number;
}

/** Телефон считается полным, когда набраны все цифры маски +7 (999) 999-99-99 */
const PHONE_DIGITS = 11;

type FieldError = 'name' | 'phone' | 'consent';

/**
 * «Купить в один клик» (Figma 595:40888, макет `56-макеты-товара/one-click-modal.png`).
 *
 * Отправляет не заявку, а ведёт в прямое оформление выбранного SKU, минуя корзину:
 * `POST /orders/one-click` считает цену, заказ создаёт оформление (`POST /orders` с `items`,
 * `accept_offer`, `accept_personal_data`) — market-docs/frontend-fulfillment-contract.md.
 * Введённое в окне едет на страницу оформления, чтобы покупатель не набирал заново.
 */
export default function OneClickModal({ open, onClose, productId, quantity }: Props) {
  const navigate = useNavigate();
  // Закрытая цена по ссылке живёт в ?offer: без него оформление посчитает обычную цену
  const offerParam = useSearchParams()[0].get('offer');
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [comment, setComment] = useState('');
  const [offer, setOffer] = useState(false);
  const [personal, setPersonal] = useState(false);
  const [errors, setErrors] = useState<FieldError[]>([]);
  const [noVariant, setNoVariant] = useState(false);
  const nameErrorId = useId();
  const phoneErrorId = useId();
  const nameRef = useRef<HTMLInputElement>(null);

  const has = (field: FieldError) => errors.includes(field);

  // Правка поля убирает только его подсказку: остальные ещё верны
  const clear = (field: FieldError) => setErrors((prev) => prev.filter((e) => e !== field));

  const submit = () => {
    const found: FieldError[] = [];
    if (!name.trim()) found.push('name');
    if (phone.replace(/\D/g, '').length !== PHONE_DIGITS) found.push('phone');
    if (!offer || !personal) found.push('consent');

    setErrors(found);
    if (found.includes('name')) nameRef.current?.focus();
    if (found.length) return;

    // У вариативного товара бэкенд требует конкретный вариант и сам его не подставит
    setNoVariant(!productId);
    if (!productId) return;

    const search = new URLSearchParams({ product_id: String(productId), quantity: String(quantity) });
    if (offerParam) search.set('offer', offerParam);
    navigate(`/checkout?${search}`, {
      state: { oneClick: { name: name.trim(), phone, comment: comment.trim() } },
    });
    onClose();
  };

  return (
    <Modal open={open} onClose={onClose} title="Купить в один клик">
      <form
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          submit();
        }}
        className="flex flex-col gap-3"
      >
        <div>
          <Input
            ref={nameRef}
            inputSize="lg"
            shape="rounded"
            tone="white"
            placeholder="ФИО"
            value={name}
            invalid={has('name')}
            aria-describedby={has('name') ? nameErrorId : undefined}
            onChange={(event) => {
              setName(event.target.value);
              clear('name');
            }}
          />
          {has('name') && (
            <p id={nameErrorId} className="mt-1.5 text-12 text-brand-red">
              Укажите ФИО
            </p>
          )}
        </div>

        <div>
          {/* Маска живёт в общем PhoneInput — тот же вид, что в оформлении и регистрации */}
          <PhoneInput
            aria-label="Телефон"
            value={phone}
            aria-invalid={has('phone') || undefined}
            aria-describedby={has('phone') ? phoneErrorId : undefined}
            onChange={(event) => {
              setPhone(event.target.value);
              clear('phone');
            }}
            className={cn(
              'h-[52px] w-full rounded-btn border bg-surface px-4 text-16 text-ink outline-none',
              'transition-colors placeholder:text-ink-secondary motion-reduce:transition-none',
              has('phone') ? 'border-brand-red' : 'border-surface-border focus:border-ink-secondary',
            )}
          />
          {has('phone') && (
            <p id={phoneErrorId} className="mt-1.5 text-12 text-brand-red">
              Укажите телефон полностью
            </p>
          )}
        </div>

        <textarea
          value={comment}
          onChange={(event) => setComment(event.target.value)}
          maxLength={1000}
          aria-label="Комментарий к заказу"
          placeholder="Комментарий к заказу"
          className="block h-[120px] w-full resize-none rounded-btn border border-surface-border bg-surface p-4 text-16 text-ink outline-none transition-colors placeholder:text-ink-secondary focus:border-ink-secondary motion-reduce:transition-none"
        />

        <div className="flex flex-col gap-2">
          <Checkbox
            checked={offer}
            onChange={(next) => {
              setOffer(next);
              if (next && personal) clear('consent');
            }}
            label={
              <>
                Я ознакомлен(-а) и принимаю условия{' '}
                <Link to="/oferta" className="underline hover:text-brand-green">
                  Публичной оферты
                </Link>
              </>
            }
          />
          <Checkbox
            checked={personal}
            onChange={(next) => {
              setPersonal(next);
              if (next && offer) clear('consent');
            }}
            label={
              <>
                Я даю согласие на обработку{' '}
                <Link to="/privacy" className="underline hover:text-brand-green">
                  персональных данных
                </Link>
              </>
            }
          />
          {has('consent') && <p className="text-12 text-brand-red">Отметьте оба согласия</p>}
        </div>

        {noVariant && (
          <p
            role="alert"
            className="rounded-btn bg-brand-red/10 px-4 py-3 text-14 text-brand-red animate-fadeIn motion-reduce:animate-none"
          >
            Сначала выберите вариант товара — цвет и размер.
          </p>
        )}

        <Button type="submit" size="lg" shape="rounded" fullWidth className="mt-1">
          Оформить заказ
        </Button>
        <p className="text-12 text-ink-secondary">
          Дальше — страница оформления: почта, доставка или самовывоз и оплата. Корзина не
          изменится.
        </p>
      </form>
    </Modal>
  );
}
