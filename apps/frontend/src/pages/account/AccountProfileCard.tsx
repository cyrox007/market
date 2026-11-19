import type { User } from '../../lib/api';
import { User as UserIcon } from 'lucide-react';
import Button from '../../components/ui/primitives/Button';
import divider from './assets/profile-divider.svg';

interface AccountProfileCardProps {
  user: Pick<User, 'name' | 'email' | 'phone'>;
  newsletter: { enabled?: boolean; isLoading: boolean; error?: unknown; onRetry: () => unknown };
  onEdit: () => void;
}

function phoneLabel(phone: string | null | undefined) {
  if (!phone?.trim()) return 'Не указан';
  const digits = phone.replace(/\D/g, '');
  if (/^[78]\d{10}$/.test(digits)) {
    return `+7 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7, 9)}-${digits.slice(9)}`;
  }
  return phone;
}

export default function AccountProfileCard({ user, newsletter, onEdit }: AccountProfileCardProps) {
  const initials = user.name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => Array.from(part)[0]).join('').toLocaleUpperCase('ru-RU');

  return (
    <section aria-label="Профиль покупателя" className="flex w-full min-w-0 flex-col gap-6 rounded-popover bg-surface-grey p-7 text-ink max-vsm:gap-4 max-vsm:px-4 max-vsm:py-5">
      <div className="flex min-w-0 items-start gap-5 max-vsm:items-center max-vsm:gap-3">
        <div aria-hidden="true" className="flex size-[88px] shrink-0 items-center justify-center rounded-full bg-brand-yellow text-24 font-bold max-vsm:size-14 max-vsm:text-18">
          {initials || <UserIcon className="size-6" />}
        </div>
        <div className="flex min-w-0 flex-1 flex-col items-start gap-3">
          <div className="flex w-full min-w-0 flex-col gap-1.5">
            <h2 className="break-words font-display text-24 font-bold tracking-[-0.02em] max-vsm:text-20">{user.name.trim() || 'Покупатель'}</h2>

          </div>
          <Button id="account-profile-edit" size="xs" shape="small" onClick={onEdit} className="shadow-[0_2px_5px_rgba(20,20,20,0.18)]">Изменить профиль</Button>
        </div>
      </div>
      <div aria-hidden="true" className="relative h-0 w-full">
        <img src={divider} alt="" className="absolute inset-x-0 -top-0.5 block h-0.5 w-full" />
      </div>
      <dl className="grid w-full min-w-0 grid-cols-3 gap-6 max-vsm:grid-cols-1 max-vsm:gap-3">
        <div className="min-w-0">
          <dt className="mb-1.5 text-14 text-ink-secondary">Email</dt>
          <dd className="text-16 font-medium [overflow-wrap:anywhere]">{user.email.trim() || 'Не указан'}</dd>
        </div>
        <div className="min-w-0">
          <dt className="mb-1.5 text-14 text-ink-secondary">Телефон</dt>
          <dd className="text-16 font-medium [overflow-wrap:anywhere]">{phoneLabel(user.phone)}</dd>
        </div>
        <div className="min-w-0">
          <dt className="mb-1.5 text-14 text-ink-secondary">Рассылка</dt>
          <dd className="text-16 font-medium [overflow-wrap:anywhere]">
            {newsletter.isLoading ? (
              <span role="status" aria-label="Загрузка рассылки" className="block h-5 w-36 max-w-full animate-pulse rounded bg-surface-border motion-reduce:animate-none" />
            ) : newsletter.error || newsletter.enabled === undefined ? (
              <div className="flex flex-wrap items-center gap-2">
                <span>Не удалось загрузить</span>
                <Button size="xs" variant="outline" shape="small" aria-label="Повторить загрузку рассылки" onClick={() => { void newsletter.onRetry(); }}>Повторить</Button>
              </div>
            ) : newsletter.enabled ? 'Акции и скидки на email' : 'Рассылка отключена'}
          </dd>
        </div>
      </dl>
    </section>
  );
}
