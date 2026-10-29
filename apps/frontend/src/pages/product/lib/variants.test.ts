import { describe, expect, it } from 'vitest';
import type { ProductVariant } from '../../../lib/api';
import { chooseValue, defaultVariant, isValueAvailable, variantFromParam } from './variants';

const attr = (attribute_slug: string, value_slug: string) => ({
  attribute_slug,
  value_slug,
  value_name: value_slug,
});

const variant = (id: number, color: string, size: string, inStock = true) =>
  ({
    id,
    sku: `SKU-${id}`,
    price: 1000,
    in_stock: inStock,
    variation_attributes: [attr('color', color), attr('size', size)],
  }) as unknown as ProductVariant;

// Серый — 90 и 120; бежевый — только 120; синий — только 90, его нет в наличии
const GREY_90 = variant(1, 'grey', '90', false);
const GREY_120 = variant(2, 'grey', '120');
const BEIGE_120 = variant(3, 'beige', '120');
const BLUE_90 = variant(4, 'blue', '90', false);
const VARIANTS = [GREY_90, GREY_120, BEIGE_120, BLUE_90];

describe('вариант по умолчанию', () => {
  it('первый в наличии', () => {
    expect(defaultVariant(VARIANTS)).toBe(GREY_120);
  });

  it('если в наличии нет ни одного — первый', () => {
    expect(defaultVariant([GREY_90, BLUE_90])).toBe(GREY_90);
  });

  it('вариантов нет — нет и выбора', () => {
    expect(defaultVariant([])).toBeNull();
  });
});

describe('вариант из адреса', () => {
  it('по id из параметра', () => {
    expect(variantFromParam(VARIANTS, '4')).toBe(BLUE_90);
  });

  it('чужой или кривой id — не найден', () => {
    expect(variantFromParam(VARIANTS, '99')).toBeNull();
    expect(variantFromParam(VARIANTS, 'abc')).toBeNull();
    expect(variantFromParam(VARIANTS, null)).toBeNull();
  });
});

describe('доступность значения', () => {
  it('значение доступно, если есть вариант с ним и остальными выбранными значениями', () => {
    expect(isValueAvailable(VARIANTS, GREY_120, 'color', 'beige')).toBe(true);
    expect(isValueAvailable(VARIANTS, GREY_120, 'size', '90')).toBe(true);
  });

  it('сочетания нет — значение недоступно', () => {
    expect(isValueAvailable(VARIANTS, GREY_120, 'color', 'blue')).toBe(false);
    expect(isValueAvailable(VARIANTS, BEIGE_120, 'size', '90')).toBe(false);
  });

  it('вариант без остатка — сочетание всё равно есть', () => {
    expect(isValueAvailable(VARIANTS, GREY_120, 'size', '90')).toBe(true);
  });
});

describe('выбор значения', () => {
  it('есть сочетание — этот вариант', () => {
    expect(chooseValue(VARIANTS, GREY_120, 'color', 'beige')).toBe(BEIGE_120);
    expect(chooseValue(VARIANTS, GREY_120, 'size', '90')).toBe(GREY_90);
  });

  it('сочетания нет — ближайший вариант с этим значением', () => {
    expect(chooseValue(VARIANTS, GREY_120, 'color', 'blue')).toBe(BLUE_90);
    expect(chooseValue(VARIANTS, BEIGE_120, 'size', '90')).toBe(GREY_90);
  });

  it('из ближайших — сначала тот, что в наличии', () => {
    const grey90InStock = variant(5, 'grey', '90');
    expect(chooseValue([...VARIANTS, grey90InStock], BEIGE_120, 'size', '90')).toBe(grey90InStock);
  });

  it('значения нет ни у одного варианта — выбор не меняется', () => {
    expect(chooseValue(VARIANTS, GREY_120, 'color', 'red')).toBe(GREY_120);
  });
});

describe('предложение комплекта без состава', () => {
  const unset = (v: ProductVariant) => ({ ...v, is_bundle_configured: false }) as ProductVariant;

  it('значение с ним — недоступно: купить такое сочетание нельзя', () => {
    expect(isValueAvailable([GREY_120, unset(BEIGE_120)], GREY_120, 'color', 'beige')).toBe(false);
  });

  it('по умолчанию не выбирается, если есть настроенное', () => {
    const kits = [unset(GREY_90), BLUE_90];
    expect(defaultVariant(kits)).toBe(BLUE_90);
  });
});
