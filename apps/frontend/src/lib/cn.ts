/**
 * Склейка классов: отбрасывает false, null, undefined и пустые строки.
 *
 * Намеренно без clsx и tailwind-merge — новых зависимостей в проект не вводим,
 * а конфликты классов решаются тем, что варианты не пересекаются по свойствам.
 * Пользовательский className всегда идёт последним, поэтому переопределяет.
 */
export type ClassValue = string | false | null | undefined;

export function cn(...classes: ClassValue[]): string {
  return classes.filter(Boolean).join(' ');
}
