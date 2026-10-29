import { describe, expect, it } from 'vitest';
import { apiErrorMessage } from './api-error';

describe('текст ошибки API', () => {
  it('сообщение бэка — в первую очередь', () => {
    expect(apiErrorMessage({ data: { message: 'Нет на складе' } }, 'Ошибка')).toBe('Нет на складе');
  });

  it('без сообщения бэка — запасной; технический текст Error («API Error: 500») не показываем', () => {
    expect(apiErrorMessage(new Error('API Error: 500 Server Error'), 'Ошибка')).toBe('Ошибка');
    expect(apiErrorMessage(undefined, 'Ошибка')).toBe('Ошибка');
    expect(apiErrorMessage({ data: {} }, 'Ошибка')).toBe('Ошибка');
  });

  it('не строка вместо сообщения — запасной текст', () => {
    expect(apiErrorMessage({ data: { message: { a: 1 } } }, 'Ошибка')).toBe('Ошибка');
  });
});
