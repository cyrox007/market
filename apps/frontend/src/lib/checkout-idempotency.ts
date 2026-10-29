const fallbackKeys = new Map<string, { key: string; expires: number }>();

/** Retain an attempt across network retries/reload without storing contact data. */
export async function checkoutIdempotencyKey(body: string): Promise<string> {
  const now = Date.now();
  const existing = fallbackKeys.get(body);
  if (existing && existing.expires > now) return existing.key;
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  let key = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  const expires = now + 5 * 60_000;
  try {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(body));
    const fingerprint = Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
    const storageKey = `checkout-attempt:${fingerprint}`;
    const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (saved && saved.expires > now && typeof saved.key === 'string') key = saved.key;
    else sessionStorage.setItem(storageKey, JSON.stringify({ key, expires }));
  } catch { /* Restricted storage: retries in the same page remain idempotent. */ }
  if (fallbackKeys.size > 20) fallbackKeys.clear();
  fallbackKeys.set(body, { key, expires });
  return key;
}
