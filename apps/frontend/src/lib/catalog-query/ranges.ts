import { RANGE_KEYS, type Range, type RangeKey } from './types';

function readBound(value: string | null): number | undefined {
  if (value === null || value === '') return undefined;
  const n = Number(value);
  return Number.isFinite(n) && n >= 0 ? Math.round(n) : undefined;
}

function readRange(params: URLSearchParams, key: RangeKey): Range {
  let min = readBound(params.get(`${key}_min`));
  let max = readBound(params.get(`${key}_max`));
  if (min !== undefined && max !== undefined && min > max) [min, max] = [max, min];
  return { ...(min !== undefined && { min }), ...(max !== undefined && { max }) };
}

export function readRanges(params: URLSearchParams): Record<RangeKey, Range> {
  return Object.fromEntries(RANGE_KEYS.map((key) => [key, readRange(params, key)])) as Record<
    RangeKey,
    Range
  >;
}

export function writeRanges(params: URLSearchParams, ranges: Record<RangeKey, Range>): void {
  for (const key of RANGE_KEYS) {
    const { min, max } = ranges[key];
    if (min !== undefined) params.set(`${key}_min`, String(min));
    if (max !== undefined) params.set(`${key}_max`, String(max));
  }
}
