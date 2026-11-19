import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import type { User, ProfileUpdateData } from '../../lib/api';
import { buildProfileUpdate, initialProfileForm } from '../../lib/profile-name';
import Input from '../../components/ui/primitives/Input';
import PhoneInput from '../../components/ui/PhoneInput';
import Button from '../../components/ui/primitives/Button';
import errorIcon from './assets/profile-error.svg';

const nameParts = [
  { key: 'first_name', label: 'Имя', autoComplete: 'given-name' },
  { key: 'last_name', label: 'Фамилия', autoComplete: 'family-name' },
  { key: 'patronymic', label: 'Отчество', autoComplete: 'additional-name' },
] as const;
const fieldOrder = ['name', 'first_name', 'last_name', 'patronymic', 'phone'] as const;
type FieldErrors = Partial<Record<typeof fieldOrder[number], string>>;
function serverFields(error: unknown): FieldErrors {
  if (!error || typeof error !== 'object' || !('data' in error)) return {};
  const data = error.data;
  if (!data || typeof data !== 'object' || !('errors' in data) || !data.errors || typeof data.errors !== 'object') return {};
  const result: FieldErrors = {};
  for (const key of fieldOrder) {
    const value = (data.errors as Record<string, unknown>)[key];
    if (Array.isArray(value) && typeof value[0] === 'string') result[key] = value[0];
    else if (typeof value === 'string') result[key] = value;
  }
  return result;
}

interface Props {
  user: Pick<User, 'name' | 'email' | 'phone' | 'first_name' | 'last_name' | 'patronymic'>;
  onSave: (data: ProfileUpdateData) => Promise<unknown>;
}

export default function AccountProfileForm({ user, onSave }: Props) {
  const [form, setForm] = useState(() => initialProfileForm(user));
  const hasStructuredName = Boolean(user.first_name || user.last_name);
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const [success, setSuccess] = useState(false);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [message, setMessage] = useState('');
  const formRef = useRef<HTMLFormElement>(null);
  useEffect(() => {
    if (pending) return;
    const key = fieldOrder.find(field => errors[field]);
    if (key) formRef.current?.querySelector<HTMLInputElement>(`#profile-${key}`)?.focus();
  }, [errors, pending]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy.current) return;
    const digits = form.phone.replace(/\D/g, '');
    const phone = digits === '7' || !digits ? undefined : `+${digits}`;
    const invalid: FieldErrors = {};
    const usingParts = hasStructuredName || Boolean(form.first_name.trim() || form.last_name.trim() || form.patronymic.trim());
    if (usingParts) {
      if (!form.first_name.trim()) invalid.first_name = 'Введите имя';
      if (!form.last_name.trim()) invalid.last_name = 'Введите фамилию';
      if ([form.last_name.trim(), form.first_name.trim(), form.patronymic.trim()].filter(Boolean).join(' ').length > 255) {
        invalid.last_name = 'Полное ФИО должно содержать не более 255 символов';
      }
    } else {
      if (!form.name.trim()) invalid.name = 'Введите имя';
      else if (form.name.trim().length > 255) invalid.name = 'Имя должно содержать не более 255 символов';
    }
    if (phone && !/^\+7\d{10}$/.test(phone)) invalid.phone = 'Введите номер полностью: +7 (XXX) XXX-XX-XX';
    setSuccess(false);
    if (Object.keys(invalid).length) {
      setMessage('Не удалось сохранить изменения. Проверьте поля, выделенные красным.');
      setErrors(invalid);
      return;
    }
    setErrors({});
    setMessage('');
    busy.current = true;
    setPending(true);
    setSuccess(false);
    try {
      await onSave(buildProfileUpdate({ ...form, phone: phone ?? '' }, hasStructuredName));
      setSuccess(true);
    } catch (error: unknown) {
      const fields = serverFields(error);
      setErrors(fields);
      setMessage(Object.keys(fields).length ? 'Не удалось сохранить изменения. Проверьте поля, выделенные красным.' : 'Не удалось сохранить изменения. Попробуйте ещё раз.');
    } finally {
      busy.current = false;
      setPending(false);
    }
  }

  return (
    <form ref={formRef} aria-label="Личные данные" noValidate onSubmit={submit} className="flex min-w-0 flex-col gap-6 rounded-popover bg-surface-grey p-7 text-ink max-vsm:px-4">
      <h2 className="text-20 font-semibold">Личные данные</h2>
      {message && <div role="alert" className="flex items-center gap-3 rounded-btn bg-brand-red/10 p-4 text-14"><img src={errorIcon} alt="" width={20} height={20} className="shrink-0" /><p>{message}</p></div>}
      {success && <p role="status" className="text-14 text-brand-green">Изменения сохранены</p>}
      <fieldset disabled={pending} className="grid min-w-0 grid-cols-1 gap-5 max-md:grid-cols-2 max-vsm:grid-cols-1">
        {!hasStructuredName && <div className="col-span-full min-w-0">
          <p className="mb-3 text-12 text-ink-secondary">Ранее ФИО сохранялось одной строкой. Можно оставить его как есть или заполнить имя и фамилию отдельно.</p>
          <label htmlFor="profile-name" className="mb-2 block text-14 text-ink-secondary">Сохранённое полное имя</label>
          <Input id="profile-name" name="name" autoComplete="name" inputSize="field" shape="rounded" tone="outline" invalid={Boolean(errors.name)} aria-describedby={errors.name ? 'profile-name-error' : undefined} value={form.name} onChange={e => { setSuccess(false); setForm({ ...form, name: e.target.value }); }} />
          {errors.name && <p id="profile-name-error" className="mt-2 text-12 text-brand-red">{errors.name}</p>}
        </div>}
        {nameParts.map(field => (
          <div key={field.key} className="min-w-0">
            <label htmlFor={`profile-${field.key}`} className="mb-2 block text-14 text-ink-secondary">{field.label}</label>
            <Input id={`profile-${field.key}`} name={field.key} autoComplete={field.autoComplete} inputSize="field" shape="rounded" tone="outline" invalid={Boolean(errors[field.key])} aria-describedby={errors[field.key] ? `profile-${field.key}-error` : field.key === 'patronymic' ? 'profile-patronymic-hint' : undefined} value={form[field.key]} onChange={event => { setSuccess(false); setForm({ ...form, [field.key]: event.target.value }); }} />
            {errors[field.key] && <p id={`profile-${field.key}-error`} className="mt-2 text-12 text-brand-red">{errors[field.key]}</p>}
            {field.key === 'patronymic' && <p id="profile-patronymic-hint" className="mt-2 text-12 text-ink-secondary">Необязательно</p>}
          </div>
        ))}
        <div className="min-w-0">
          <label htmlFor="profile-email" className="mb-2 block text-14 text-ink-secondary">Email</label>
          <Input id="profile-email" type="email" inputSize="field" shape="rounded" tone="outline" value={user.email} readOnly aria-describedby="profile-email-hint" />
          <p id="profile-email-hint" className="mt-2 text-12 text-ink-secondary">Email нельзя изменить</p>
        </div>
        <div className="min-w-0">
          <label htmlFor="profile-phone" className="mb-2 block text-14 text-ink-secondary">Телефон</label>
          <PhoneInput id="profile-phone" autoComplete="tel" aria-invalid={Boolean(errors.phone) || undefined} aria-describedby={errors.phone ? 'profile-phone-error' : 'profile-phone-hint'} value={form.phone} onChange={e => { setSuccess(false); setForm(current => ({ ...current, phone: e.target.value })); }} className={`h-[47px] w-full min-w-0 rounded-btn border bg-surface px-5 text-14 outline-none ${errors.phone ? 'border-brand-red' : 'border-surface-border focus:border-ink-secondary'}`} />
          {errors.phone ? <p id="profile-phone-error" className="mt-2 text-12 text-brand-red">{errors.phone}</p> : <p id="profile-phone-hint" className="mt-2 text-12 text-ink-secondary">Необязательно. Пустое поле сохранит текущий номер.</p>}
        </div>
      </fieldset>
      <div className="flex flex-wrap gap-3 max-vsm:flex-col">
        <Button type="submit" size="lg" shape="rounded" disabled={pending} className="w-[302px] max-w-full shadow-[0_2px_10px_rgba(20,20,20,0.18)] max-vsm:w-full max-vsm:h-11 max-vsm:text-14">{pending ? 'Сохранение…' : 'Сохранить изменения'}</Button>
        <Button type="button" variant="ghost" disabled={pending} onClick={() => { setForm(initialProfileForm(user)); setSuccess(false); setErrors({}); setMessage(''); }}>Отменить изменения</Button>
      </div>
    </form>
  );
}
