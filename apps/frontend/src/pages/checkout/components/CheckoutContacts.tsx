import { useId, useLayoutEffect, useRef, useState } from 'react';
import Input from '../../../components/ui/primitives/Input';
import {
  checkoutContactErrors,
  formatCheckoutPhoneInput,
  normalizeCheckoutPhone,
} from '../contactValidation';

export interface CheckoutContact {
  lastName: string;
  firstName: string;
  middleName: string;
  phone: string;
  email: string;
}
export function CheckoutContacts({
  value,
  onChange,
  showErrors = false,
}: {
  value: CheckoutContact;
  onChange: (value: CheckoutContact) => void;
  showErrors?: boolean;
}) {
  const id = useId();
  const phoneRef = useRef<HTMLInputElement>(null);
  const phoneSelection = useRef<{ start: number; end: number } | null>(null);
  useLayoutEffect(() => {
    if (phoneSelection.current && phoneRef.current) {
      const { start, end } = phoneSelection.current;
      phoneRef.current.setSelectionRange(start, end);
      phoneSelection.current = null;
    }
  });
  const [touched, setTouched] = useState<Partial<Record<keyof CheckoutContact, boolean>>>({});
  const errors = checkoutContactErrors(value);
  const fields = [
    ['lastName', 'Фамилия', 'family-name', 'text'],
    ['firstName', 'Имя', 'given-name', 'text'],
    ['middleName', 'Отчество', 'additional-name', 'text'],
    ['phone', 'Телефон', 'tel', 'tel'],
    ['email', 'Почта', 'email', 'email'],
  ] as const;
  return (
    <section aria-labelledby="checkout-contacts" className="space-y-4">
      <h2 id="checkout-contacts" className="text-20 font-semibold max-vsm:text-16">
        Личные данные
      </h2>
      <div className="space-y-2">
        {fields.map(([key, label, autoComplete, type]) => {
          const error = (showErrors || touched[key]) && errors[key];
          const errorId = `${id}-${key}-error`;
          return (
            <div key={key}>
              <Input
                ref={key === 'phone' ? phoneRef : undefined}
                aria-label={label}
                name={key}
                placeholder={`${label}${key === 'middleName' ? '' : '*'}`}
                required={key !== 'middleName'}
                autoComplete={autoComplete}
                type={type}
                inputMode={key === 'phone' ? 'tel' : key === 'email' ? 'email' : undefined}
                maxLength={key === 'email' ? 255 : undefined}
                autoCapitalize={key === 'email' ? 'none' : undefined}
                spellCheck={key === 'email' ? false : undefined}
                invalid={!!error}
                aria-describedby={error ? errorId : undefined}
                onBlur={() => {
                  if (key === 'phone')
                    onChange({ ...value, phone: normalizeCheckoutPhone(value.phone) });
                  setTouched((previous) => ({ ...previous, [key]: true }));
                }}
                onInvalid={() => setTouched((previous) => ({ ...previous, [key]: true }))}
                shape="rounded"
                inputSize="field"
                tone="outline"
                wrapperClassName="max-vsm:h-[41px]"
                value={value[key]}
                onChange={(event) => {
                  if (key !== 'phone') {
                    onChange({ ...value, [key]: event.target.value });
                    return;
                  }
                  const input = event.currentTarget;
                  const raw = input.value;
                  const deleting =
                    (event.nativeEvent as InputEvent).inputType?.startsWith('delete') ?? false;
                  const next = formatCheckoutPhoneInput(raw, deleting);
                  if (next !== raw) {
                    const filtered = raw.replace(/[^\d+()\s-]/g, '');
                    const position = (offset: number) =>
                      next === filtered
                        ? raw.slice(0, offset).replace(/[^\d+()\s-]/g, '').length
                        : Math.max(2, next.length - raw.slice(offset).replace(/\D/g, '').length);
                    phoneSelection.current = {
                      start: position(input.selectionStart ?? raw.length),
                      end: position(input.selectionEnd ?? raw.length),
                    };
                  }
                  onChange({ ...value, phone: next });
                }}
              />
              {error && (
                <p id={errorId} className="mt-1 text-12 text-brand-red">
                  {error}
                </p>
              )}
            </div>
          );
        })}
      </div>
    </section>
  );
}
