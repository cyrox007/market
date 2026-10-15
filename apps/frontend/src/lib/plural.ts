/** Склонение по числу: plural(21, ['товар', 'товара', 'товаров']) → «товар» */
export function plural(n: number, [one, few, many]: readonly [string, string, string]): string {
  const mod100 = Math.abs(n) % 100;
  const mod10 = mod100 % 10;
  if (mod100 >= 11 && mod100 <= 14) return many;
  if (mod10 === 1) return one;
  if (mod10 >= 2 && mod10 <= 4) return few;
  return many;
}
