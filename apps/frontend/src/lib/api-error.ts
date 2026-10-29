/** Текст ошибки для покупателя: сообщение бэка, иначе запасной (текст Error у fetchAPI технический) */
export function apiErrorMessage(err: unknown, fallback: string): string {
  const fromApi = (err as { data?: { message?: unknown } } | null | undefined)?.data?.message;
  if (typeof fromApi === 'string' && fromApi) return fromApi;
  return fallback;
}
