/**
 * Склейка классов: отбрасывает false, null, undefined и пустые строки.
 *
 * Намеренно без clsx и tailwind-merge. Отсюда правило: два класса на одно свойство
 * в одной строке — ошибка, порядок в атрибуте ничего не решает. Выбирайте один
 * тернарником. Почему не подключили библиотеку — market-docs/16-код-ревью.md.
 */
export type ClassValue = string | false | null | undefined;

export function cn(...classes: ClassValue[]): string {
  return classes.filter(Boolean).join(' ');
}
