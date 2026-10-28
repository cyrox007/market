const STORAGE_KEY = 'guest-order-access';
const memory = new Map<number, { token: string; expires: number }>();

export function saveGuestOrderToken(id: number, token: string): void {
  if (!Number.isSafeInteger(id) || !/^[a-f0-9]{64}$/.test(token)) return;
  memory.set(id, { token, expires: Date.now() + 30 * 86400_000 });
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    const entries = Object.entries({ ...saved, [id]: memory.get(id) }).slice(-20);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(Object.fromEntries(entries)));
  } catch { /* Keep access for the current page when browser storage is unavailable. */ }
}

export function guestOrderHeaders(id: number): Record<string, string> {
  let entry = memory.get(id);
  try { entry = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')[id] ?? entry; } catch { /* restricted storage */ }
  return entry && entry.expires > Date.now() && /^[a-f0-9]{64}$/.test(entry.token)
    ? { 'X-Order-Token': entry.token } : {};
}

/** Import an email capability, then return a URL without the secret fragment. */
export function consumeGuestOrderLink(id: number, href: string): string | null {
  const url = new URL(href);
  const fragment = new URLSearchParams(url.hash.slice(1));
  if (!fragment.has('access')) return null;
  const token = fragment.get('access');
  if (token && /^[a-f0-9]{64}$/.test(token)) saveGuestOrderToken(id, token);
  fragment.delete('access');
  const rest = fragment.toString();
  return url.pathname + url.search + (rest ? `#${rest}` : '');
}
