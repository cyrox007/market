import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { api } from '../../lib/api';
import { runSessionTask } from '../../lib/session-queue';
import Input from '../../components/ui/primitives/Input';
import Button from '../../components/ui/primitives/Button';
import errorIcon from './assets/profile-error.svg';

const fields = [
  {
    key: 'current_password',
    label: 'Текущий пароль',
    placeholder: 'Введите текущий пароль',
    complete: 'current-password',
  },
  {
    key: 'password',
    label: 'Новый пароль',
    placeholder: 'Введите новый пароль',
    complete: 'new-password',
  },
  {
    key: 'password_confirmation',
    label: 'Повторите пароль',
    placeholder: 'Повторите новый пароль',
    complete: 'new-password',
  },
] as const;
type Field = (typeof fields)[number]['key'];
const emptyForm = { current_password: '', password: '', password_confirmation: '' };
export default function AccountPasswordForm() {
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState<Partial<Record<Field, string>>>({});
  const [message, setMessage] = useState('');
  const [success, setSuccess] = useState(false);
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const root = useRef<HTMLFormElement>(null);
  useEffect(() => {
    if (pending) return;
    const field = fields.find(({ key }) => errors[key]);
    if (field) root.current?.querySelector<HTMLInputElement>(`#settings-${field.key}`)?.focus();
  }, [errors, pending]);
  async function submit(event: FormEvent) {
    event.preventDefault();
    if (busy.current) return;
    const invalid: Partial<Record<Field, string>> = {};
    if (!form.current_password) invalid.current_password = 'Введите текущий пароль';
    if (!form.password) invalid.password = 'Введите новый пароль';
    else if (form.password.length < 8) invalid.password = 'Минимум 8 символов';
    if (!form.password_confirmation) invalid.password_confirmation = 'Повторите новый пароль';
    else if (form.password_confirmation !== form.password)
      invalid.password_confirmation = 'Пароли не совпадают';
    setSuccess(false);
    setErrors(invalid);
    setMessage('');
    if (Object.keys(invalid).length) {
      setMessage('Не удалось изменить пароль. Проверьте поля, выделенные красным.');
      return;
    }
    busy.current = true;
    setPending(true);
    try {
      await runSessionTask(() => api.auth.changePassword(form));
      setForm(emptyForm);
      setSuccess(true);
    } catch (failure: unknown) {
      const data = (failure as { data?: { errors?: Record<string, unknown> } })?.data;
      const rejected: Partial<Record<Field, string>> = {};
      for (const { key } of fields) {
        const value = data?.errors?.[key];
        if (Array.isArray(value) && typeof value[0] === 'string') rejected[key] = value[0];
        else if (typeof value === 'string') rejected[key] = value;
      }
      setErrors(rejected);
      setMessage(
        Object.keys(rejected).length
          ? 'Не удалось изменить пароль. Проверьте поля, выделенные красным.'
          : 'Не удалось изменить пароль. Попробуйте ещё раз.',
      );
    } finally {
      busy.current = false;
      setPending(false);
    }
  }
  return (
    <form
      ref={root}
      aria-label="Смена пароля"
      noValidate
      onSubmit={submit}
      className="flex min-w-0 flex-col gap-6 rounded-popover bg-surface-grey p-7 text-ink max-vsm:px-4"
    >
      <div>
        <h2 className="text-20 font-semibold">Смена пароля</h2>
        <p id="settings-password-hint" className="mt-1.5 text-14 text-ink-secondary">
          Минимум 8 символов
        </p>
      </div>
      {message && (
        <div
          role="alert"
          className="flex items-center gap-3 rounded-btn bg-brand-red/10 p-4 text-14"
        >
          <img src={errorIcon} alt="" width={20} height={20} className="shrink-0" />
          <p>{message}</p>
        </div>
      )}
      {success && (
        <p role="status" className="text-14 text-brand-green">
          Пароль успешно изменён
        </p>
      )}
      <fieldset
        disabled={pending}
        className="grid min-w-0 grid-cols-1 gap-4 max-md:grid-cols-3 max-vsm:grid-cols-1"
      >
        {fields.map((field) => (
          <div key={field.key} className="min-w-0">
            <label
              htmlFor={`settings-${field.key}`}
              className="mb-2 block text-14 text-ink-secondary"
            >
              {field.label}
            </label>
            <Input
              id={`settings-${field.key}`}
              name={field.key}
              type="password"
              autoComplete={field.complete}
              placeholder={field.placeholder}
              inputSize="field"
              shape="rounded"
              tone="outline"
              value={form[field.key]}
              invalid={Boolean(errors[field.key])}
              aria-describedby={
                errors[field.key]
                  ? `settings-${field.key}-error`
                  : field.key === 'password'
                    ? 'settings-password-hint'
                    : undefined
              }
              onChange={(event) => {
                setSuccess(false);
                setForm({ ...form, [field.key]: event.target.value });
              }}
            />
            {errors[field.key] && (
              <p id={`settings-${field.key}-error`} className="mt-2 text-12 text-brand-red">
                {errors[field.key]}
              </p>
            )}
          </div>
        ))}
      </fieldset>
      <Button
        type="submit"
        variant="muted"
        shape="rounded"
        size="lg"
        disabled={pending}
        className="w-[302px] max-w-full max-vsm:h-11 max-vsm:w-full max-vsm:text-14"
      >
        {pending ? 'Сохранение…' : 'Изменить пароль'}
      </Button>
    </form>
  );
}
