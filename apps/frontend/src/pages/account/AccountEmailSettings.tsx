import { useEffect, useRef, useState, type FormEvent } from 'react';
import { api, type AccountEmailStatus } from '../../lib/api';
import Button from '../../components/ui/primitives/Button';
import Input from '../../components/ui/primitives/Input';
import { runSessionRead, runSessionTask } from '../../lib/session-queue';

function EmailChangeDeadline({ expiresAt }: { expiresAt: string }) {
  const [now, setNow] = useState(() => Date.now());
  // AccountEmail currently returns the raw SQL timestamp; backend app.timezone is UTC.
  const timestamp = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(expiresAt)
    ? `${expiresAt.replace(' ', 'T')}Z` : expiresAt;
  const deadline = new Date(timestamp);
  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, []);
  if (!Number.isFinite(deadline.getTime())) return null;
  if (deadline.getTime() <= now) return <p>Срок подтверждения истёк. Запросите смену почты повторно.</p>;
  return <p>Подтвердите новый адрес до <time dateTime={timestamp}>{deadline.toLocaleString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Moscow' })}</time> МСК.</p>;
}

export default function AccountEmailSettings({ userId, email }: { userId: number; email: string }) {
  const [status, setStatus] = useState<AccountEmailStatus | null>(null);
  const [newEmail, setNewEmail] = useState('');
  const [password, setPassword] = useState('');
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const busyRef = useRef(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    let active = true;
    setStatus(null);
    setError('');
    runSessionRead(() => api.accountEmail.status())
      .then((data) => {
        if (active) setStatus(data);
      })
      .catch(() => {
        if (active) setError('Не удалось загрузить настройки почты.');
      });
    return () => {
      active = false;
    };
  }, [userId, email, revision]);

  async function perform(action: () => Promise<{ message: string }>) {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusy(true);
    setError('');
    setMessage('');
    try {
      await runSessionTask(async () => {
        const result = await action();
        setPassword('');
        setMessage(result.message);
        setEditing(false);
        try {
          // Direct read inside this mutation; waiting on the same queue here would deadlock.
          setStatus(await api.accountEmail.status());
        } catch {
          setStatus(null);
          setError('Действие выполнено, но не удалось обновить настройки почты. Повторите загрузку.');
        }
      });
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string; errors?: Record<string, string[]> } }).data;
      setError(
        data?.errors?.current_password?.[0] ||
          data?.errors?.email?.[0] ||
          data?.message ||
          'Не удалось выполнить действие. Повторите попытку.',
      );
    } finally {
      busyRef.current = false;
      setBusy(false);
    }
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    void perform(() => api.accountEmail.change(newEmail, password));
  }

  return (
    <section
      aria-labelledby="account-email-heading"
      className="space-y-4 rounded-popover bg-surface-grey p-7 text-ink max-vsm:px-4"
    >
      <h2 id="account-email-heading" className="text-20 font-semibold">
        Электронная почта
      </h2>
      <p className="break-words">{status?.email ?? email}</p>
      {status && (
        <p className="text-14 text-ink-secondary">
          {status.is_verified ? 'Почта подтверждена' : 'Почта не подтверждена'}
        </p>
      )}
      {error && (
        <div role="alert" className="text-14 text-brand-red">
          {error}{' '}
          {!status && (
            <Button type="button" size="xs" onClick={() => setRevision((value) => value + 1)}>
              Повторить загрузку
            </Button>
          )}
        </div>
      )}
      {message && (
        <p role="status" className="text-14 text-brand-green">
          {message}
        </p>
      )}
      {status?.pending_email && (
        <div className="space-y-2 text-14">
          <p>
            Ожидает подтверждения: <strong className="break-words">{status.pending_email}</strong>.
            До подтверждения действует прежняя почта.
          </p>
          {status.pending_expires_at && <EmailChangeDeadline key={status.pending_expires_at} expiresAt={status.pending_expires_at} />}
          <Button
            type="button"
            size="xs"
            variant="outline"
            disabled={busy}
            onClick={() => void perform(api.accountEmail.cancel)}
          >
            Отменить смену почты
          </Button>
        </div>
      )}
      {status && (
        <div className="flex flex-wrap gap-3">
          {!status.is_verified && (
            <Button
              type="button"
              size="xs"
              disabled={busy}
              onClick={() => void perform(api.accountEmail.verify)}
            >
              Подтвердить почту
            </Button>
          )}
          <Button
            type="button"
            size="xs"
            variant="outline"
            disabled={busy}
            onClick={() => {
              setEditing((value) => !value);
              setPassword('');
            }}
          >
            Изменить почту
          </Button>
        </div>
      )}
      {editing && (
        <form onSubmit={submit} className="space-y-3">
          <label className="block space-y-1 text-14">
            Новая почта
            <Input
              type="email"
              inputSize="field"
              shape="rounded"
              tone="outline"
              disabled={busy}
              autoComplete="email"
              required
              maxLength={255}
              value={newEmail}
              onChange={(event) => setNewEmail(event.target.value)}
            />
          </label>
          <label className="block space-y-1 text-14">
            Текущий пароль
            <Input
              type="password"
              inputSize="field"
              shape="rounded"
              tone="outline"
              disabled={busy}
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
            />
          </label>
          <p className="text-12 text-ink-secondary">
            Подтвердите новый адрес по ссылке из письма. После смены почты потребуется войти заново.
          </p>
          <Button type="submit" size="xs" disabled={busy}>
            {busy ? 'Отправляем…' : 'Отправить подтверждение'}
          </Button>
        </form>
      )}
    </section>
  );
}
