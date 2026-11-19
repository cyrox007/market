import type { ColorSolution } from './api';

export function solutionBackground(solution: Pick<ColorSolution, 'base_colors'>): string {
  const hexes = solution.base_colors
    .map((color) => color.hex)
    .filter((hex): hex is string => Boolean(hex && /^#(?:[\da-f]{3}|[\da-f]{6})$/i.test(hex)));
  if (!hexes.length) return '#f5f5f5';
  if (hexes.length === 1) return hexes[0];
  return `linear-gradient(90deg, ${hexes.map((hex, index) => `${hex} ${(index * 100) / hexes.length}% ${((index + 1) * 100) / hexes.length}%`).join(', ')})`;
}
