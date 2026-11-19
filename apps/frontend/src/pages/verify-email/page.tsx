import { useEffect, useRef, useState } from 'react';
import { api } from '../../lib/api';
import { useAuth } from '../../hooks/useAuth';
import Button from '../../components/ui/primitives/Button';
import { PAGE_CONTAINER } from '../../lib/layout';

export default function VerifyEmail() {
  const [token, setToken] = useState('');
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const { refreshUser } = useAuth();
  const consumed = useRef(false);
  useEffect(() => {
    if (consumed.current) return;
    consumed.current = true;
    const value = new URLSearchParams(window.location.hash.slice(1)).get('token') || '';
    setToken(value);
    // Do not expose the bearer token to referrers or retain it in the browser URL.
    window.history.replaceState(
      window.history.state,
      '',
      window.location.pathname + window.location.search,
    );
    if (!value)
      setError('В ссылке нет кода подтверждения. Запросите новое письмо в личном кабинете.');
  }, []);

  async function confirm() {
    if (busy || done || !token) return;
    setBusy(true);
    setError('');
    try {
      const result = await api.accountEmail.confirm(token);
      setDone(true);
      setMessage(result.message);
      await refreshUser(true);
    } catch (err: unknown) {
      const data = (err as { data?: { message?: string; errors?: Record<string, string[]> } }).data;
      setError(
        data?.errors?.token?.[0] ||
          data?.errors?.email?.[0] ||
          data?.message ||
          'Не удалось подтвердить почту. Повторите попытку.',
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className={`${PAGE_CONTAINER} py-10`}>
      <section className="mx-auto max-w-xl space-y-5 rounded-popover bg-surface-grey p-7">
        <h1 className="text-24 font-semibold">Подтверждение почты</h1>
        {error && (
          <p role="alert" className="text-brand-red">
            {error}
          </p>
        )}
        {message && <p role="status">{message}</p>}
        {!done && token && (
          <Button type="button" disabled={busy} onClick={() => void confirm()}>
            {busy ? 'Подтверждаем…' : 'Подтвердить почту'}
          </Button>
        )}
        <div className="flex flex-wrap gap-3">
          <Button to="/account" variant="outline">
            Личный кабинет
          </Button>
          <Button to="/login" variant="outline">
            Войти
          </Button>
        </div>
      </section>
    </div>
  );
}
