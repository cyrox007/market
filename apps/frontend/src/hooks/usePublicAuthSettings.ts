import useSWR from 'swr';

export interface PublicAuthSettings {
  registration_enabled: boolean;
  login_enabled: boolean;
}

async function loadPublicAuthSettings(): Promise<PublicAuthSettings> {
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '');
  const response = await fetch(`${base}/auth/settings`, {
    credentials: 'include',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) throw new Error('Не удалось проверить доступность входа и регистрации.');
  const body = await response.json();
  if (typeof body.data?.registration_enabled !== 'boolean' || typeof body.data?.login_enabled !== 'boolean') {
    throw new Error('Некорректные настройки входа и регистрации.');
  }
  return body.data;
}

export function usePublicAuthSettings() {
  const { data, error, mutate } = useSWR('/auth/settings', loadPublicAuthSettings, {
    refreshInterval: 30_000,
    revalidateOnFocus: true,
    shouldRetryOnError: false,
  });
  return {
    ready: Boolean(data),
    registrationEnabled: data?.registration_enabled === true,
    loginEnabled: data?.login_enabled === true,
    error,
    reload: () => mutate(),
  };
}
