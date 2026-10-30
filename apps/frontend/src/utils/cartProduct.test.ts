import { describe, expect, it } from 'vitest';
import { isVariableParent } from './cartProduct';

// Вид товара бэк задаёт флагами is_variable / is_variant (market-docs/69)
describe('вариативный товар в каталоге', () => {
  it('вариативный товар — по флагам', () => {
    expect(isVariableParent({ is_variable: true, is_variant: false })).toBe(true);
  });

  it('вариант и простой товар — не вариативный товар', () => {
    expect(isVariableParent({ is_variable: false, is_variant: true })).toBe(false);
    expect(isVariableParent({ is_variable: false, is_variant: false })).toBe(false);
  });

  it('простой товар не становится вариативным из-за побочных полей', () => {
    const simple = {
      id: 3,
      is_variable: false,
      is_variant: false,
      first_available_variant_id: 9,
      variation_attributes: [{}],
      variants: [{}],
    };
    expect(isVariableParent(simple)).toBe(false);
  });
});
