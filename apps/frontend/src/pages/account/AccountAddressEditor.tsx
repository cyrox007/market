import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { api, type Address, type AddressData } from '../../lib/api';
import { runSessionTask } from '../../lib/session-queue';
import CheckoutAddress from '../../components/CheckoutAddress';
import Input from '../../components/ui/primitives/Input';
import Button from '../../components/ui/primitives/Button';
import Checkbox from '../../components/ui/primitives/Checkbox';
import Sheet from '../../components/ui/primitives/Sheet';
import closeIcon from './assets/address-x.svg';

type Field = 'title' | 'apartment' | 'entrance' | 'floor' | 'directory_external_id';
interface Props {
  open: boolean;
  narrow: boolean;
  contentPadding: number;
  address: Address | null;
  onClose: () => void;
  onSaved: () => Promise<unknown>;
}
export default function AccountAddressEditor({ open, narrow, contentPadding, address, onClose, onSaved }: Props) {
  const [form, setForm] = useState({
    title: address?.title || '',
    apartment: address?.apartment || '',
    entrance: address?.entrance || '',
    floor: address?.floor || '',
  });
  const [destination, setDestination] = useState<string | null>(
    address?.directory_external_id || null,
  );
  const [destinationChanged, setDestinationChanged] = useState(false);
  const [primary, setPrimary] = useState(Boolean(address?.is_default));
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const [persisted, setPersisted] = useState<number | null>(null);
  const persistedId = useRef<number | null>(null);
  const defaultDone = useRef(Boolean(address?.is_default));
  const [message, setMessage] = useState('');
  const [errors, setErrors] = useState<Partial<Record<Field, string>>>({});
  const formRef = useRef<HTMLFormElement>(null);
  useEffect(() => {
    if (pending) return;
    const key = Object.keys(errors)[0];
    if (key) formRef.current?.querySelector<HTMLElement>(`[data-field="${key}"] input`)?.focus();
  }, [errors, pending]);
  async function submit(e: FormEvent) {
    e.preventDefault();
    if (busy.current) return;
    const invalid: Partial<Record<Field, string>> = {};
    if ((!address || destinationChanged) && !destination)
      invalid.directory_external_id = 'Выберите дом из адресного справочника.';
    for (const key of ['title', 'apartment', 'entrance', 'floor'] as const)
      if (form[key].length > (key === 'title' ? 255 : 64))
        invalid[key] = key === 'title' ? 'Не более 255 символов' : 'Не более 64 символов';
    setErrors(invalid);
    setMessage('');
    if (Object.keys(invalid).length) {
      setMessage('Проверьте заполнение адреса.');
      return;
    }
    busy.current = true;
    setPending(true);
    try {
      if (!persistedId.current) {
        const data: AddressData = {
          ...form,
          title: form.title.trim(),
          ...((!address || destinationChanged) && destination
            ? { directory_external_id: destination }
            : {}),
        };
        const result = await runSessionTask(() =>
          address ? api.addresses.update(address.id, data) : api.addresses.create(data),
        );
        persistedId.current = result.address.id;
        setPersisted(result.address.id);
      }
      if (primary && !defaultDone.current) {
        await runSessionTask(() => api.addresses.setDefault(persistedId.current!));
        defaultDone.current = true;
      }
      await onSaved();
      onClose();
    } catch (failure: unknown) {
      if (persistedId.current) {
        setMessage(
          'Адрес сохранён, но не удалось завершить обновление. Нажмите «Повторить»: новый адрес повторно не создаётся.',
        );
        try {
          await onSaved();
        } catch {
          /* The list owns its read error and retry. */
        }
      } else {
        const data = (failure as { data?: { message?: string; errors?: Record<string, string[]> } })
          ?.data;
        const fields: Partial<Record<Field, string>> = {};
        for (const key of [
          'title',
          'apartment',
          'entrance',
          'floor',
          'directory_external_id',
        ] as const)
          if (typeof data?.errors?.[key]?.[0] === 'string') fields[key] = data.errors[key][0];
        setErrors(fields);
        setMessage(data?.message || 'Не удалось сохранить адрес. Попробуйте ещё раз.');
      }
    } finally {
      busy.current = false;
      setPending(false);
    }
  }
  function close() {
    if (!busy.current) onClose();
  }
  return (
    <Sheet
      open={open}
      onClose={close}
      side={narrow ? 'bottom' : 'center'}
      contentPadding={contentPadding}
      title={address ? 'Изменить адрес' : 'Новый адрес'}
      className="md:w-[600px] md:rounded-popover max-md:rounded-t-popover"
      headerAction={
        <button
          type="button"
          aria-label="Закрыть"
          disabled={pending}
          onClick={close}
          className="rounded-full p-1 focus-visible:outline-brand-green disabled:opacity-50"
        >
          <img src={closeIcon} alt="" width={24} height={24} />
        </button>
      }
    >
      <form
        ref={formRef}
        aria-label={address ? 'Изменить адрес' : 'Новый адрес'}
        noValidate
        onSubmit={submit}
        className="flex min-w-0 flex-col gap-6"
      >
        {message && (
          <p role="alert" className="rounded-btn bg-brand-red/10 p-3 text-14">
            {message}
          </p>
        )}
        <fieldset disabled={pending || persisted !== null} className="flex min-w-0 flex-col gap-4">
          <div data-field="title">
            <label htmlFor="address-title" className="mb-2 block text-14 text-ink-secondary">
              Название адреса
            </label>
            <Input
              id="address-title"
              inputSize="field"
              shape="rounded"
              tone="outline"
              placeholder="Например, Дом или Работа"
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              invalid={Boolean(errors.title)}
              aria-describedby={errors.title ? 'address-title-error' : undefined}
            />
            {errors.title && (
              <p id="address-title-error" className="mt-2 text-12 text-brand-red">
                {errors.title}
              </p>
            )}
          </div>
          <div data-field="directory_external_id">
            <CheckoutAddress
              appearance="account"
              initialAddress={address}
              onChange={(id) => {
                setDestinationChanged(true);
                setDestination(id);
              }}
            />
            {errors.directory_external_id && (
              <p role="alert" className="mt-2 text-12 text-brand-red">
                {errors.directory_external_id}
              </p>
            )}
          </div>
          <div className="grid min-w-0 grid-cols-3 gap-3">
            {(['apartment', 'entrance', 'floor'] as const).map((key, i) => (
              <div key={key} data-field={key} className="min-w-0">
                <label htmlFor={`address-${key}`} className="mb-2 block text-14 text-ink-secondary">
                  {['Квартира', 'Подъезд', 'Этаж'][i]}
                </label>
                <Input
                  id={`address-${key}`}
                  inputSize="field"
                  shape="rounded"
                  tone="outline"
                  placeholder={i ? '—' : 'Кв.'}
                  value={form[key]}
                  onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                  invalid={Boolean(errors[key])}
                  aria-describedby={errors[key] ? `address-${key}-error` : undefined}
                />
                {errors[key] && (
                  <p id={`address-${key}-error`} className="mt-2 text-12 text-brand-red">
                    {errors[key]}
                  </p>
                )}
              </div>
            ))}
          </div>
          <Checkbox
            checked={primary}
            readOnly={Boolean(address?.is_default)}
            aria-disabled={Boolean(address?.is_default) || undefined}
            onChange={(value) => {
              if (!address?.is_default) setPrimary(value);
            }}
            label="Сделать основным адресом"
          />
        </fieldset>
        <div className="grid grid-cols-2 gap-3">
          <Button
            type="button"
            variant="outline"
            shape="rounded"
            disabled={pending}
            onClick={close}
            className="min-w-0 max-vsm:px-2 max-vsm:text-14"
          >
            Отмена
          </Button>
          <Button
            type="submit"
            shape="rounded"
            disabled={pending}
            className="min-w-0 shadow-[0_2px_10px_rgba(20,20,20,0.18)] max-vsm:px-2 max-vsm:text-14"
          >
            {pending
              ? 'Сохранение…'
              : persisted
                ? 'Повторить'
                : address
                  ? 'Сохранить адрес'
                  : 'Добавить адрес'}
          </Button>
        </div>
      </form>
    </Sheet>
  );
}
