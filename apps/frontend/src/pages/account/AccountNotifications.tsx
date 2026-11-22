import { useRef, useState } from 'react';
import type { NotificationSettings } from '../../lib/api';
import Checkbox from '../../components/ui/primitives/Checkbox';
import Button from '../../components/ui/primitives/Button';
import { AccountDataError } from './AccountDataError';

const choices = [
  {
    key: 'email_promotions',
    label: 'Акции и скидки на email',
    detail: 'Не чаще одного раза в неделю',
  },
  {
    key: 'email_order_notifications',
    label: 'Статус заказа на email',
    detail: 'Уведомления об изменении статуса заказа',
  },
] as const;
interface Props {
  settings?: NotificationSettings;
  isLoading: boolean;
  error?: unknown;
  onRetry: () => unknown;
  onSave: (patch: Partial<NotificationSettings>) => Promise<unknown>;
}
export default function AccountNotifications({
  settings,
  isLoading,
  error,
  onRetry,
  onSave,
}: Props) {
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const [success, setSuccess] = useState(false);
  const [failedPatch, setFailedPatch] = useState<Partial<NotificationSettings> | null>(null);
  async function save(patch: Partial<NotificationSettings>) {
    if (busy.current || !settings) return;
    busy.current = true;
    setPending(true);
    setSuccess(false);
    setFailedPatch(null);
    try {
      await onSave(patch);
      setSuccess(true);
    } catch {
      setFailedPatch(patch);
    } finally {
      busy.current = false;
      setPending(false);
    }
  }
  return (
    <section
      aria-labelledby="settings-notifications-heading"
      className="flex min-w-0 flex-col gap-5 rounded-popover bg-surface-grey p-7 text-ink max-vsm:px-4"
    >
      <h2 id="settings-notifications-heading" className="text-20 font-semibold">
        Уведомления
      </h2>
      {Boolean(error || (!settings && !isLoading)) && (
        <AccountDataError label="Не удалось загрузить настройки уведомлений" onRetry={onRetry} />
      )}
      {failedPatch && (
        <div
          role="alert"
          className="flex flex-wrap items-center gap-3 rounded-btn bg-brand-red/10 p-4 text-14"
        >
          <span>Не удалось сохранить настройки. Изменение отменено.</span>
          <Button type="button" size="xs" variant="outline" onClick={() => void save(failedPatch)}>
            Повторить сохранение
          </Button>
        </div>
      )}
      {!settings ? (
        isLoading ? (
          <div role="status" aria-label="Загрузка настроек уведомлений" className="space-y-4">
            {choices.map(({ key }) => (
              <div
                key={key}
                className="h-10 w-full max-w-lg animate-pulse rounded-btn bg-surface-border motion-reduce:animate-none"
              />
            ))}
          </div>
        ) : null
      ) : (
        <div className="flex flex-col gap-4">
          {choices.map((choice) => (
            <div key={choice.key}>
              <Checkbox
                label={choice.label}
                checked={settings[choice.key] ?? false}
                disabled={pending || typeof settings[choice.key] !== 'boolean'}
                onChange={(value) => void save({ [choice.key]: value })}
                aria-describedby={choice.detail ? `settings-${choice.key}-hint` : undefined}
                className="items-start"
              />
              {choice.detail && (
                <p
                  id={`settings-${choice.key}-hint`}
                  className="mt-1 pl-[30px] text-12 text-ink-secondary"
                >
                  {choice.detail}
                </p>
              )}
            </div>
          ))}
        </div>
      )}
      {pending && (
        <p role="status" className="text-14 text-ink-secondary">
          Сохраняем настройки…
        </p>
      )}
      {success && (
        <p role="status" className="text-14 text-brand-green">
          Настройки уведомлений сохранены
        </p>
      )}
    </section>
  );
}

