/** `next` после входа: только путь этого сайта, иначе null — защита от увода на чужой адрес */
export function safeNext(raw: string | null | undefined): string | null {
  if (!raw || !raw.startsWith('/') || raw.startsWith('//') || raw.startsWith('/\\')) return null;
  return raw;
}

export const withNext = (path: string, next: string | null) =>
  next ? `${path}?next=${encodeURIComponent(next)}` : path;
