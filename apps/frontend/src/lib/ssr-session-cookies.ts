/** Cookie names used by the current Laravel API and existing deployments. */
export function hasAuthSession(cookies: Record<string, unknown> | undefined): boolean {
  return ['laravel-session', 'laravel_session', 'svetofor-session'].some((name) =>
    typeof cookies?.[name] === 'string' && Boolean(cookies[name]),
  );
}
