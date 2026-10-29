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
