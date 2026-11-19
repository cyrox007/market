import { expect, it } from 'vitest';
import { hasAuthSession } from './ssr-session-cookies';
it('recognizes the Laravel session cookie issued by the local API and supported deployment aliases', () => {
  expect(hasAuthSession({ 'laravel-session': 'session-value' })).toBe(true);
  expect(hasAuthSession({ 'laravel_session': 'session-value' })).toBe(true);
  expect(hasAuthSession({ 'svetofor-session': 'session-value' })).toBe(true);
  expect(hasAuthSession({ 'XSRF-TOKEN': 'csrf-only' })).toBe(false);
  expect(hasAuthSession({ 'laravel-session': '' })).toBe(false);
});
