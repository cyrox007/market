/** «23500» → «23 500». Обычный пробел, а не неразрывный, как у Intl: в поле его печатают руками */
export function groupDigits(digits: string): string {
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

export const formatNumber = (n: number) => groupDigits(String(n));
