import { useEffect, useState, type FormEvent } from 'react';
import { api, type AccountEmailStatus } from '../../lib/api';
import Button from '../../components/ui/primitives/Button';

export default function AccountEmailSettings({ userId, email }: { userId: number; email: string }) {
  const [status, setStatus] = useState<AccountEmailStatus | null>(null);
  const [newEmail, setNewEmail] = useState('');
  const [password, setPassword] = useState('');
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    let active = true;
    setStatus(null);
    setError('');
    api.accountEmail
      .status()
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
    if (busy) return;
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const result = await action();
      setPassword('');
      setMessage(result.message);
      const fresh = await api.accountEmail.status();
      setStatus(fresh);
      setEditing(false);
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string; errors?: Record<string, string[]> } }).data;
      setError(
        data?.errors?.current_password?.[0] ||
          data?.errors?.email?.[0] ||
          data?.message ||
          'Не удалось выполнить действие. Повторите попытку.',
      );
    } finally {
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
      className="mb-6 space-y-4 rounded-popover bg-surface-grey p-7 text-ink max-vsm:px-4"
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
            <input
              type="email"
              autoComplete="email"
              required
              maxLength={255}
              value={newEmail}
              onChange={(event) => setNewEmail(event.target.value)}
              className="block w-full rounded-btn border border-surface-border bg-surface px-4 py-3"
            />
          </label>
          <label className="block space-y-1 text-14">
            Текущий пароль
            <input
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="block w-full rounded-btn border border-surface-border bg-surface px-4 py-3"
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
