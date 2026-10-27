import { useId, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import PhoneInput from '../../../components/ui/PhoneInput';
import { Button, Checkbox, Input, Modal } from '../../../components/ui/primitives';
import { FOOTER_CONTACTS } from '../../../components/layout/Footer/lib/links';
import { cn } from '../../../lib/cn';

interface Props {
  open: boolean;
  onClose: () => void;
}

/** Телефон считается полным, когда набраны все цифры маски +7 (999) 999-99-99 */
const PHONE_DIGITS = 11;

type FieldError = 'name' | 'phone' | 'consent';

/**
 * «Купить в один клик» (Figma 595:40888, макет `56-макеты-товара/one-click-modal.png`).
 *
 * Отправлять заявку некуда: `POST /orders/one-click` только считает цену и велит слать
 * `POST /orders`, а тому нужны почта и способ доставки, которых в окне нет (`44`, сверка 24.10).
 * Поэтому проверенная форма показывает сообщение и телефон, а введённое остаётся на месте —
 * когда приём заявок появится, меняется только `submit`.
 */
export default function OneClickModal({ open, onClose }: Props) {
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [comment, setComment] = useState('');
  const [offer, setOffer] = useState(false);
  const [personal, setPersonal] = useState(false);
  const [errors, setErrors] = useState<FieldError[]>([]);
  const [failed, setFailed] = useState(false);
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
    // Поля в порядке — отправлять некуда, показываем сообщение (см. комментарий выше)
    setFailed(found.length === 0);
    if (found.includes('name')) nameRef.current?.focus();
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

        {failed && (
          <p
            role="alert"
            className="rounded-btn bg-brand-red/10 px-4 py-3 text-14 text-brand-red animate-fadeIn motion-reduce:animate-none"
          >
            Оформление в один клик пока не работает. Позвоните нам:{' '}
            <a
              href={`tel:${FOOTER_CONTACTS.phone.replace(/\D/g, '')}`}
              className="font-semibold underline"
            >
              {FOOTER_CONTACTS.phone}
            </a>{' '}
            — или добавьте товар в корзину и оформите заказ обычным способом.
          </p>
        )}

        <Button type="submit" size="lg" shape="rounded" fullWidth className="mt-1">
          Оформить заказ
        </Button>
      </form>
    </Modal>
  );
}
