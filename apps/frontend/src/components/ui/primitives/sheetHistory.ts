export const HISTORY_KEY = 'sheet';

/** Сверху истории — запись открытой шторки: переход по ссылке должен её заменить, а не лечь сверху */
export function isSheetHistoryEntry(): boolean {
  return Boolean((window.history.state as Record<string, unknown> | null)?.[HISTORY_KEY]);
}
